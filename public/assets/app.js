'use strict';
const $ = (id) => document.getElementById(id);
const ui = {file:$('pdf-input'),analyze:$('analyze-button'),generate:$('generate-button'),error:$('error-box'),overlay:$('progress-overlay')};
let csrf='', selectedFile=null, currentId=null, currentState=null, pollTimer=null, busy=false, startedAt=0, pollFailures=0, selectedImage=-1;
let sourceType='pdf',keyReady=false;
function updateColorUI(){
  $('background-color-control').hidden=document.querySelector('input[name="mood"]:checked').value!=='color';
  $('background-color-value').value=$('background-color').value.toUpperCase();
  document.querySelector('.swatch.color').style.backgroundColor=$('background-color').value;
  if(currentState?.images[selectedImage]?.options.transparent_background){
    $('cover-image').style.backgroundColor=Keycover.previewColor({mood:document.querySelector('input[name="mood"]:checked').value,background_color:$('background-color').value});
  }
}
let recent=[];
try { recent=JSON.parse(localStorage.getItem('keycover.recent')||'[]').filter(j=>j.at>Date.now()-7*86400000).slice(0,12); } catch { recent=[]; }
function saveRecent(s) {
  recent=[{id:s.id,name:s.filename,at:s.created_at*1000},...recent.filter(j=>j.id!==s.id)].slice(0,12);
  try { localStorage.setItem('keycover.recent',JSON.stringify(recent));localStorage.setItem('keycover.active',s.id); } catch { /* Storage may be disabled in a private browser. */ }
  renderRecent();
}
function renderRecent() {
  const select=$('recent-jobs');select.replaceChildren(new Option('이 브라우저에서 만든 표지 보기',''));
  recent.forEach(j=>select.add(new Option(j.name,j.id)));select.value=currentId||'';$('recent-panel').hidden=!recent.length;
}
function error(message,check=false) { $('error-message').textContent=message;ui.error.hidden=false;$('check-status').hidden=!check;$('live-status').textContent=message; }
function clearError(){ui.error.hidden=true;$('check-status').hidden=true;}
async function request(action,body=null,extra={}) {
  const options={credentials:'same-origin',cache:'no-store',...extra};
  if(body!==null) {options.method='POST';options.headers={'Content-Type':'application/json','X-CSRF-Token':csrf,...extra.headers};options.body=body instanceof Blob?body:JSON.stringify(body);}
  let response;
  try { response=await fetch(`api.php?action=${action}`,options); } catch {throw new Error('서버에 연결하지 못했습니다. 인터넷 연결을 확인해 주세요.');}
  let data;
  try { data=await response.json(); } catch {throw new Error('서버 응답을 읽지 못했습니다. 잠시 후 다시 시도해 주세요.');}
  if(!response.ok) {if(response.status===401)Keycover.lock();const e=new Error(data.error||'요청을 처리하지 못했습니다.');e.status=response.status;throw e;}
  return data;
}
function descriptionChanged() {
  return sourceType==='text' && (!currentState || currentState.source_type!=='text' || $('description').value.trim()!==currentState.description);
}
function updateSourceUI() {
  const isText=sourceType==='text', changed=descriptionChanged();
  document.querySelectorAll('input[name="source_type"]').forEach(el=>el.checked=el.value===sourceType);
  $('description-input').hidden=!isText;
  $('drop-zone').hidden=isText||!!currentState;
  $('file-info').hidden=isText||(!currentState&&!selectedFile);
  $('new-job').hidden=!currentState&&!selectedFile&&!$('description').value;
  $('description-count').textContent=`${Array.from($('description').value).length.toLocaleString()} / 2,000자`;
  ui.analyze.hidden=!!currentState?.analysis&&!changed;
  ui.analyze.textContent=isText?(busy&&currentState?.status==='analyzing'?'설명 구상 중':'설명으로 구상하기 ↗'):(currentState?.status==='failed'&&!currentState.analysis?'PDF 다시 분석하기':'PDF 내용 분석하기 ↗');
}
function setBusy(value) {
  if(value&&!busy)$('elapsed').textContent='0분 00초 경과';
  busy=value; ui.file.disabled=value;$('new-job').disabled=value;$('recent-jobs').disabled=value;
  $('save-api-key').disabled=value;$('clear-api-key').disabled=value;$('openai-key').disabled=value;$('publish-gallery').disabled=value;
  $('cover-settings').disabled=value;$('source-settings').disabled=value;
  const validText=$('description').value.trim()!==''&&Array.from($('description').value.trim()).length<=2000;
  ui.analyze.disabled=value||!csrf||!keyReady||(sourceType==='text'?!validText:(!selectedFile && !(currentState?.status==='failed'&&!currentState.analysis)));
  ui.generate.disabled=value||!csrf||!keyReady||!currentState?.analysis||descriptionChanged();
  updateSourceUI();
  ui.overlay.hidden=!value;
}
function imageUrl(n,download=false){return `file.php?id=${encodeURIComponent(currentId)}&kind=image&n=${n}${download?'&download=1':''}`;}
function selectImage(n) {
  if(!currentState?.images[n])return;
  selectedImage=n;$('cover-image').src=imageUrl(n);$('cover-image').alt=`${currentState.analysis.title} 강의의 글자 없는 3D 아이소메트릭 표지 ${n+1}`;
  $('cover-image').style.backgroundColor=currentState.images[n].options.transparent_background?Keycover.previewColor(currentState.images[n].options):'';
  $('placeholder').hidden=true;$('download').hidden=false;$('download').href=imageUrl(n,true);$('view-gallery').href=`gallery.html?cover=${currentId}-${n}`;$('view-gallery').hidden=currentState.images[n].gallery_public!==true;
  $('preview-badge').textContent='2560 × 1440 · PNG';
  document.querySelectorAll('#variants button').forEach((b,i)=>{b.classList.toggle('active',i===n);b.setAttribute('aria-pressed',String(i===n));});
}
function renderAnalysis(a,pages) {
  $('analysis-panel').hidden=!a;$('analysis-content').hidden=!a;
  const isText=sourceType==='text';
  $('analysis-title').textContent=isText?'설명 기반 구상':'PDF 분석';
  $('analysis-count').textContent=a?(isText?'설명 구상 완료':`${pages}페이지 전체 분석 완료`):'';
  $('page-analysis').hidden=isText;
  if(!a)return;
  $('lecture-title').textContent=a.title;$('summary').textContent=a.summary;$('visual-direction').textContent=a.visual_direction;
  $('keywords').replaceChildren(...a.keywords.map(k=>{const el=document.createElement('span');el.textContent=k;return el;}));
  $('page-total').textContent=`${pages} / ${pages}`;
  $('page-summaries').replaceChildren(...(a.page_summaries||[]).map(p=>{
    const li=document.createElement('li'),number=document.createElement('b'),div=document.createElement('div'),title=document.createElement('strong'),detail=document.createElement('p');
    number.textContent=String(p.page).padStart(2,'0');title.textContent=p.topic;detail.textContent=p.visual_motif;div.append(title,detail);li.append(number,div);return li;
  }));
}
function renderState(s) {
  const wasImages=currentState?.id===s.id?currentState.images.length:0;
  if(currentState?.id!==s.id) {
    sourceType=s.source_type||'pdf';
    if(sourceType==='text')$('description').value=s.description||'';
  }
  if(currentState?.id!==s.id&&s.options) {
    document.querySelectorAll('input[name="complexity"]').forEach(el=>el.checked=el.value===(s.options.complexity??'medium'));
    document.querySelectorAll('input[name="color_theme"]').forEach(el=>el.checked=el.value===(s.options.color_theme??'blue'));
    $('transparent-background').checked=s.options.transparent_background===true;
    document.querySelectorAll('input[name="image_share"]').forEach(el=>el.checked=Number(el.value)===(s.options.image_share??50));
    document.querySelectorAll('input[name="space"]').forEach(el=>el.checked=el.value===s.options.space);
    document.querySelectorAll('input[name="mood"]').forEach(el=>el.checked=el.value===s.options.mood);
    $('background-color').value=s.options.background_color||'#b1a1df';
    updateColorUI();
    $('notes').value=s.options.notes;
  }
  currentState=s;currentId=s.id;selectedFile=null;saveRecent(s);
  $('file-info').hidden=false;$('file-name').textContent=s.filename;$('file-meta').textContent=`${s.pages}페이지 · ${(s.bytes/1048576).toFixed(1)}MB`;
  if(sourceType==='pdf')$('pdf-preview').src=`file.php?id=${s.id}&kind=preview`;
  else $('pdf-preview').removeAttribute('src');
  $('pdf-preview').hidden=sourceType==='text'||!s.analysis;
  renderAnalysis(s.analysis,s.pages);
  $('variants').replaceChildren(...s.images.map((image,n)=>{
    const button=document.createElement('button'),img=document.createElement('img');button.type='button';button.setAttribute('aria-label',`생성한 표지 ${n+1} 보기`);img.src=imageUrl(n);img.alt=`표지 ${n+1} 미리보기`;button.append(img);button.addEventListener('click',()=>selectImage(n));return button;
  }));
  $('variants').hidden=s.images.length<2;
  if(s.images.length) selectImage(s.images.length>wasImages||selectedImage<0? s.images.length-1:Math.min(selectedImage,s.images.length-1));
  else {$('cover-image').src='assets/cover-studio-simple.png';$('cover-image').alt='풍경 표지를 만드는 복셀 모니터와 키보드, 마우스';$('placeholder').hidden=false;$('download').hidden=true;$('view-gallery').hidden=true;$('preview-badge').textContent='미리보기';}
  const running=['analyzing','generating'].includes(s.status);
  if(running)startedAt=s.operation_started_at?s.operation_started_at*1000:(startedAt||Date.now());
  $('progress-title').textContent=s.status==='analyzing'?(sourceType==='text'?'설명을 표지 장면으로 정리하고 있어요':'강의의 이야기를 읽고 있어요'):'표지의 첫 장면을 만들고 있어요';
  $('progress-detail').textContent=s.phase;
  ui.generate.textContent=s.images.length?'✦ 다른 버전 만들기':'✦ 3D 복셀 커버 만들기';
  setBusy(running);
  if(s.status==='failed')error(s.error||'작업을 마치지 못했습니다. 다시 시도해 주세요.');
  else clearError();
  if(!running) {startedAt=0;$('live-status').textContent=s.phase;}
  return running;
}
async function poll() {
  clearTimeout(pollTimer);if(!currentId)return;
  const id=currentId;
  try {
    const s=await request(`status&id=${encodeURIComponent(id)}`);if(currentId!==id)return;pollFailures=0;
    if(renderState(s))pollTimer=setTimeout(poll,3000);
  } catch(e) {
    if(currentId!==id)return;
    if(e.status===401)return;
    if([403,404].includes(e.status)) {
      recent=recent.filter(j=>j.id!==id);try{localStorage.setItem('keycover.recent',JSON.stringify(recent));}catch{}busy=false;reset();error(e.message);return;
    }
    pollFailures++;error(`${e.message} 작업은 서버에서 계속될 수 있습니다.`,true);
    if(pollFailures<5)pollTimer=setTimeout(poll,5000);
    else {setBusy(false);ui.generate.disabled=true;ui.analyze.disabled=true;ui.file.disabled=true;$('new-job').disabled=true;}
  }
}
function chooseFile(file) {
  if(busy||sourceType!=='pdf'||!file)return;
  clearError();
  if(!file.name.toLowerCase().endsWith('.pdf')||(file.type&&file.type!=='application/pdf')){selectedFile=null;setBusy(false);error('PDF 파일만 선택할 수 있습니다.');ui.file.value='';return;}
  if(!file.size||file.size>25*1048576){selectedFile=null;setBusy(false);error('PDF는 25MB 이하의 비어 있지 않은 파일로 올려 주세요.');ui.file.value='';return;}
  selectedFile=file;currentId=null;currentState=null;selectedImage=-1;clearTimeout(pollTimer);
  $('file-info').hidden=false;$('file-name').textContent=file.name;$('file-meta').textContent=`${(file.size/1048576).toFixed(1)}MB · 분석 시 페이지 수를 확인해요`;$('pdf-preview').hidden=true;
  renderAnalysis(null,0);ui.analyze.hidden=false;setBusy(false);
}
function reset(keepDescription=false) {
  if(busy)return;
  clearTimeout(pollTimer);currentId=null;currentState=null;selectedFile=null;selectedImage=-1;ui.file.value='';
  if(!keepDescription)$('description').value='';
  try{localStorage.removeItem('keycover.active');}catch{}
  $('drop-zone').hidden=false;$('file-info').hidden=true;$('new-job').hidden=true;
  ui.analyze.hidden=false;ui.analyze.textContent='PDF 내용 분석하기 ↗';ui.generate.textContent='✦ 3D 복셀 커버 만들기';
  $('cover-image').src='assets/cover-studio-simple.png';$('cover-image').style.backgroundColor='';$('cover-image').alt='풍경 표지를 만드는 복셀 모니터와 키보드, 마우스';$('placeholder').hidden=false;$('download').hidden=true;$('view-gallery').hidden=true;$('variants').hidden=true;
  $('preview-badge').textContent='미리보기';$('live-status').textContent='';
  renderAnalysis(null,0);clearError();setBusy(false);renderRecent();if(sourceType==='text')$('description').focus();else ui.file.focus();
}
document.querySelectorAll('input[name="source_type"]').forEach(el=>el.addEventListener('change',()=>{
  if(busy)return;sourceType=el.value;reset(true);
}));
$('description').addEventListener('input',()=>{
  setBusy(false);clearError();
  renderAnalysis(descriptionChanged()?null:currentState?.analysis,currentState?.pages||0);
});
ui.file.addEventListener('change',()=>chooseFile(ui.file.files[0]));
const drop=$('drop-zone');
['dragenter','dragover'].forEach(type=>drop.addEventListener(type,e=>{e.preventDefault();if(!busy)drop.classList.add('dragover');}));
['dragleave','drop'].forEach(type=>drop.addEventListener(type,()=>drop.classList.remove('dragover')));
drop.addEventListener('drop',e=>{e.preventDefault();if(e.dataTransfer.files.length!==1){error('PDF 한 개씩 올려 주세요.');return;}chooseFile(e.dataTransfer.files[0]);});
window.addEventListener('dragover',e=>e.preventDefault());window.addEventListener('drop',e=>e.preventDefault());
ui.analyze.addEventListener('click',async()=>{
  if(busy||ui.analyze.disabled)return;clearError();startedAt=Date.now();setBusy(true);
  $('progress-title').textContent=sourceType==='text'?'설명을 준비하고 있어요':'PDF를 준비하고 있어요';
  $('progress-detail').textContent=sourceType==='text'?'입력 내용에 맞는 표지 구상을 만듭니다.':'파일과 페이지 수를 확인합니다.';
  try {
    if(currentId&&!selectedFile&&!descriptionChanged())await request('reanalyze',{id:currentId});
    else {
      const data=sourceType==='text'?await request('describe',{description:$('description').value.trim()}):await request('upload',selectedFile,{headers:{'Content-Type':'application/pdf','X-Filename':encodeURIComponent(selectedFile.name)}});
      currentId=data.id;try{localStorage.setItem('keycover.active',currentId);}catch{}
    }
    await poll();
  }catch(e){startedAt=0;setBusy(false);error(e.message);}
});
document.querySelectorAll('input[name="mood"]').forEach(el=>el.addEventListener('change',updateColorUI));
$('background-color').addEventListener('input',updateColorUI);
updateColorUI();
ui.generate.addEventListener('click',async()=>{
  if(busy||!currentId||ui.generate.disabled)return;clearError();startedAt=Date.now();setBusy(true);$('progress-title').textContent='표지 생성을 시작하고 있어요';$('progress-detail').textContent='입력 내용의 핵심을 장면에 담습니다.';
  try {
    const mood=document.querySelector('input[name="mood"]:checked').value;
    await request('generate',{id:currentId,color_theme:document.querySelector('input[name="color_theme"]:checked').value,complexity:document.querySelector('input[name="complexity"]:checked').value,transparent_background:$('transparent-background').checked,image_share:Number(document.querySelector('input[name="image_share"]:checked').value),space:document.querySelector('input[name="space"]:checked').value,mood,...(mood==='color'?{background_color:$('background-color').value}:{}),notes:$('notes').value,publish_gallery:$('publish-gallery').checked});
    await poll();$('cover-canvas').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'center'});
  }catch(e){startedAt=0;setBusy(false);error(e.message);}
});
$('download').addEventListener('click',e=>{e.preventDefault();if(currentId&&selectedImage>=0)Keycover.download(imageUrl(selectedImage,true),`keycover-${currentId}-${selectedImage+1}.png`,$('download'),error);});
$('new-job').addEventListener('click',()=>reset());
$('check-status').addEventListener('click',()=>{pollFailures=0;setBusy(true);poll();});
$('recent-jobs').addEventListener('change',e=>{if(e.target.value&&!busy){currentId=e.target.value;currentState=null;selectedImage=-1;poll();}});
setInterval(()=>{if(busy&&startedAt){const seconds=Math.floor((Date.now()-startedAt)/1000);$('elapsed').textContent=`${Math.floor(seconds/60)}분 ${String(seconds%60).padStart(2,'0')}초 경과`; }},1000);
async function initApp(){
  renderRecent();
  try{const session=await request('session');csrf=session.csrf;applyKeyStatus(session);setBusy(false);if(new URLSearchParams(location.search).has('new')){reset();history.replaceState(null,'','./');return;}let active=new URLSearchParams(location.search).get('job');try{active=active||localStorage.getItem('keycover.active');}catch{}if(active&&/^[a-f0-9]{32}$/.test(active)){currentId=active;await poll();}}
  catch(e){error(e.message+' 화면을 새로고침해 주세요.');}
}
function applyKeyStatus(data){
  keyReady=data.key_ready===true;
  $('api-key-status').textContent=keyReady?'API 키 설정됨 · 세션에서만 사용':'API 키를 입력해 주세요.';
  $('clear-api-key').hidden=!keyReady;
}
$('api-key-form').addEventListener('submit',async e=>{
  e.preventDefault();if(busy||!csrf)return;
  const value=$('openai-key').value.trim();$('openai-key').value='';
  try{applyKeyStatus(await request('set-key',{key:value}));clearError();}catch(e){error(e.message);}finally{setBusy(false);}
});
$('clear-api-key').addEventListener('click',async()=>{
  if(busy)return;
  try{applyKeyStatus(await request('clear-key',{}));}catch(e){error(e.message);}finally{$('openai-key').value='';setBusy(false);}
});
Keycover.start({
  init:initApp,
  lock(){csrf='';clearTimeout(pollTimer);},
  error:message=>error(message)
});
