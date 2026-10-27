// The browser must never save an authentication/error response as file.json.
const vm=require('node:vm'),fs=require('node:fs'),assert=require('node:assert/strict');
const source=fs.readFileSync('public/assets/common.js','utf8');
function fixture(responses){
  const elements=new Map(),saved=[],errors=[];
  const element=()=>({dataset:{},textContent:'PNG 다운로드',hidden:false,value:'',setAttribute(){},removeAttribute(){},remove(){}});
  const document={getElementById(id){if(!elements.has(id))elements.set(id,element());return elements.get(id);},body:{append(){}},createElement(){return {...element(),click(){saved.push({href:this.href,name:this.download});}};}};
  const context={window:{},document,fetch:async()=>{const r=responses.shift();if(r instanceof Error)throw r;assert.ok(r,'Unexpected network request');return r;},URL:{createObjectURL:()=> 'blob:test',revokeObjectURL(){}},Uint8Array,setTimeout:()=>0};
  vm.runInNewContext(source,context);return {api:context.window.Keycover,saved,errors,button:element(),responses};
}
(async()=>{
  for(const [name,response] of [
    ['expired login',new Response('{"error":"login required"}',{status:401,headers:{'Content-Type':'application/json'}})],
    ['wrong owner',new Response('{"error":"forbidden"}',{status:403,headers:{'Content-Type':'application/json'}})],
    ['unexpected JSON',new Response('{}',{headers:{'Content-Type':'application/json'}})],
    ['invalid PNG',new Response('not an image',{headers:{'Content-Type':'image/png'}})],
    ['network failure',new Error('offline')]
  ]){
    const f=fixture([response]);await f.api.download('/file.php','test.png',f.button,m=>f.errors.push(m));assert.equal(f.saved.length,0,name);assert.equal(f.errors.length,1,name);assert.equal(f.button.textContent,'PNG 다운로드');console.log('PASS '+name+' never saved');
  }
  const f=fixture([new Response(new Uint8Array([137,80,78,71,13,10,26,10,0]),{headers:{'Content-Type':'image/png'}})]);
  await f.api.download('/file.php','test.png',f.button,m=>f.errors.push(m));assert.equal(f.saved.length,1);assert.equal(f.saved[0].name,'test.png');assert.equal(f.errors.length,0);console.log('PASS authenticated PNG saved with PNG filename');
  const publicPng=fixture([new Response(new Uint8Array([137,80,78,71,13,10,26,10,0]),{headers:{'Content-Type':'image/png'}})]);
  let initialized=0;
  await publicPng.api.start({public:true,init(){initialized++;},error:m=>publicPng.errors.push(m)});
  assert.equal(initialized,1);assert.equal(publicPng.responses.length,1,'Public startup must not fetch authentication');
  await publicPng.api.download('/gallery-file.php','public.png',publicPng.button,m=>publicPng.errors.push(m));
  assert.equal(publicPng.saved.length,1);assert.equal(publicPng.errors.length,0);assert.equal(publicPng.responses.length,0);
  console.log('PASS public gallery starts and downloads without authentication requests');
  for(const response of [new Response('{}',{headers:{'Content-Type':'application/json'}}),new Response('bad',{headers:{'Content-Type':'image/png'}}),new Response('{"error":"unexpected unauthorized"}',{status:401})]){
    const f=fixture([response]);await f.api.start({public:true,init(){},error:m=>f.errors.push(m)});
    await f.api.download('/gallery-file.php','public.png',f.button,m=>f.errors.push(m));
    assert.equal(f.saved.length,0);assert.equal(f.errors.length,1);assert.equal(f.responses.length,0);
  }
  console.log('PASS public download rejects error responses and invalid PNGs without a login form');
})().catch(e=>{console.error(e);process.exitCode=1;});
