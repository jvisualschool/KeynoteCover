"""Isolated local HTTP security checks. Synthetic credentials; no OpenAI calls."""
import http.cookiejar,json,os,secrets,socket,subprocess,tempfile,time,urllib.request,urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
checks=[]
def check(name,ok):
    if not ok:raise AssertionError(name)
    checks.append(name)
class Client:
    def __init__(self,base):
        self.base=base;self.jar=http.cookiejar.CookieJar();self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
    def call(self,path,data=None,csrf=None,origin=None):
        h={};payload=None
        if data is not None:payload=json.dumps(data).encode();h['Content-Type']='application/json'
        if csrf is not None:h['X-CSRF-Token']=csrf
        if origin:h['Origin']=origin
        request=urllib.request.Request(self.base+path,payload,h)
        try:r=self.http.open(request,timeout=5)
        except urllib.error.HTTPError as e:r=e
        with r:
            raw=r.read()
            try:body=json.loads(raw)
            except (ValueError,UnicodeDecodeError):body=raw
            return r.status,body,r.headers
with tempfile.TemporaryDirectory(prefix='keycover-http-') as temporary:
    data=Path(temporary)/'data'
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    base=f'http://127.0.0.1:{port}'
    env={**os.environ,'KC_ORIGIN':base,'KC_DATA_DIR':str(data)}
    # CLI server serves only public/. No request in this test starts an AI worker.
    process=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(ROOT/'public')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    try:
        a=Client(base);b=Client(base)
        for _ in range(60):
            try:code,session,headers=a.call('/api.php?action=session');break
            except OSError:time.sleep(.05)
        else:raise RuntimeError('Local test server did not start')
        token=session['csrf'];_,other,_=b.call('/api.php?action=session')
        check('Fresh session has no API key',code==200 and session['key_ready'] is False)
        check('Session identity isolated',token!=other['csrf'])
        check('HttpOnly and SameSite cookies',all('HttpOnly' in c._rest and c._rest.get('SameSite')=='Strict' for c in a.jar))
        check('No-store responses',headers.get('Cache-Control')=='no-store')
        check('CSP header present',"connect-src 'self'" in headers.get('Content-Security-Policy',''))
        check('Missing key blocks paid operation',a.call('/api.php?action=describe',{'description':'fixture'},token)[0]==409)
        key='sk-'+('fixture'*8)
        check('CSRF required for key submission',a.call('/api.php?action=set-key',{'key':key})[0]==403)
        check('Cross-origin submission rejected',a.call('/api.php?action=set-key',{'key':key},token,'https://untrusted.example')[0]==403)
        check('Header injection rejected',a.call('/api.php?action=set-key',{'key':key+'\r\nX-Test: 1'},token)[0]==400)
        code,result,_=a.call('/api.php?action=set-key',{'key':key},token,base)
        check('Own key accepted without returning credential',code==200 and result['key_ready'] and key not in json.dumps(result))
        check('Key isolated from other sessions',b.call('/api.php?action=session')[1]['key_ready'] is False)
        check('Key encrypted at rest',all(key.encode() not in p.read_bytes() for p in data.rglob('*') if p.is_file()))
        check('Missing CSRF blocks mutation',a.call('/api.php?action=generate',{'id':'0'*32})[0]==403)
        check('Path traversal rejected',a.call('/file.php?id=../../app/bootstrap.php')[0]==400)
        check('Unknown private file rejected',a.call('/file.php?id='+'0'*32)[0]==404)
        check('Invalid description rejected before any AI call',a.call('/api.php?action=describe',{'description':[]},token)[0]==400)
        job=secrets.token_hex(16);job_dir=data/'jobs'/job;job_dir.mkdir()
        owner=None
        # Read only this test's freshly created session to seed an owned job.
        sid=next(c.value for c in a.jar if c.name=='keycover_session')
        code_php="session_save_path($argv[1]);session_name('keycover_session');session_id($argv[2]);session_start();echo $_SESSION['owner'];session_write_close();"
        owner=subprocess.check_output(['php','-r',code_php,str(data/'sessions'),sid],text=True)
        state={'id':job,'owner':owner,'ip_hash':'private','status':'ready','updated_at':int(time.time()),'analysis':{},'images':[]}
        (job_dir/'state.json').write_text(json.dumps(state))
        check('Owned job readable',a.call('/api.php?action=status&id='+job)[0]==200)
        check('Other session cannot read job',b.call('/api.php?action=status&id='+job)[0]==403)
        check('Public state omits owner and IP',not any(k in a.call('/api.php?action=status&id='+job)[1] for k in ['owner','ip_hash']))
        cover=job+'-0';cover_dir=data/'gallery'/cover;cover_dir.mkdir()
        record={'id':cover,'owner':owner,'job_id':job,'index':0,'title':'Synthetic fixture','filename':'fixture','pages':0,'source_type':'text','keywords':['fixture'],'prompt':'fixture','created_at':int(time.time()),'width':2560,'height':1440,'bytes':8}
        (cover_dir/'record.json').write_text(json.dumps(record));(cover_dir/'image.png').write_bytes(bytes([137,80,78,71,13,10,26,10]))
        check('Public gallery read works',b.call('/api.php?action=gallery')[0]==200)
        check('Public cover excludes ownership secret','owner' not in b.call('/api.php?action=cover&id='+cover)[1])
        check('Other session cannot delete cover',b.call('/api.php?action=delete-cover',{'id':cover},other['csrf'])[0]==403)
        check('Owner can hide own cover',a.call('/api.php?action=delete-cover',{'id':cover},token)[0]==200)
        check('Hidden cover is not publicly accessible',b.call('/gallery-file.php?id='+cover)[0]==404)
        check('Other session cannot restore cover',b.call('/api.php?action=restore-cover',{'id':cover},other['csrf'])[0]==403)
        check('Owner can restore own cover',a.call('/api.php?action=restore-cover',{'id':cover},token)[0]==200)
        check('Gallery POST rejected',a.call('/api.php?action=gallery',{},token)[0]==405)
        check('GET cannot set key',a.call('/api.php?action=set-key')[0]==405)
        code,result,_=a.call('/api.php?action=clear-key',{},token)
        check('Key deletion revokes future use',code==200 and result['key_ready'] is False and a.call('/api.php?action=describe',{'description':'fixture'},token)[0]==409)
        check('App source is outside web root',a.call('/app/bootstrap.php')[0]==404)
        print(json.dumps({'passed':len(checks),'checks':checks,'openai_calls':0},ensure_ascii=False))
    finally:
        process.terminate();process.wait(timeout=5)
