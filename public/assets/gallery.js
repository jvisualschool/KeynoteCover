'use strict';
const $=id=>document.getElementById(id);
let page=1,pages=1,query='',sequence=0,detailSequence=0,detail=null,lastHidden=null;
const date=value=>new Date(value*1000).toLocaleString('ko-KR',{year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'});
const size=value=>`${(value/1048576).toFixed(1)} MB`;
function error(message){$('gallery-error-message').textContent=message;$('gallery-error').hidden=false;}
function fileUrl(id,thumb=false){return `gallery-file.php?id=${encodeURIComponent(id)}${thumb?'&thumb=1':''}`;}
function updateUrl(cover=null){
  const params=new URLSearchParams();if(query)params.set('q',query);if(page>1)params.set('page',String(page));if(cover)params.set('cover',cover);
  history.replaceState(null,'',`gallery.html${params.size?'?'+params:''}`);
}
function download(item,button,onError=error){return Keycover.download(fileUrl(item.id)+'&download=1',`keycover-${item.id}.png`,button,onError);}
function card(item){
  const article=document.createElement('article');article.className='cover-card';
  const preview=document.createElement('button');preview.type='button';preview.className='cover-card-preview';preview.setAttribute('aria-label',`${item.title} 상세정보`);
  const img=document.createElement('img');img.src=fileUrl(item.id,true);img.alt=item.title;img.loading='lazy';img.width=640;img.height=360;preview.append(img);preview.addEventListener('click',()=>openCover(item.id));
  const body=document.createElement('div');body.className='cover-card-body';
  const title=document.createElement('h2');title.textContent=item.title;
  const meta=document.createElement('p');meta.className='cover-card-meta';meta.textContent=`${date(item.created_at)} · ${size(item.bytes)}`;
  const actions=document.createElement('div');actions.className='cover-card-actions';
  const show=document.createElement('button');show.type='button';show.className='text-button';show.textContent='상세정보 · 프롬프트';show.addEventListener('click',()=>openCover(item.id));
  const save=document.createElement('button');save.type='button';save.className='download-button';save.textContent='PNG 다운로드 ↓';save.addEventListener('click',()=>download(item,save));
  actions.append(show,save);body.append(title,meta,actions);article.append(preview,body);return article;
}
async function load(){
  const seq=++sequence;$('gallery-error').hidden=true;$('gallery-status').textContent='불러오는 중…';$('gallery-grid').setAttribute('aria-busy','true');
  $('previous-page').disabled=true;$('next-page').disabled=true;
  try{
    const data=await Keycover.json(`api.php?action=gallery&page=${page}&q=${encodeURIComponent(query)}`);
    if(seq!==sequence)return;
    page=data.page;pages=data.pages;updateUrl(new URLSearchParams(location.search).get('cover'));
    $('gallery-count').textContent=String(data.all_total);
    $('gallery-grid').replaceChildren(...data.items.map(card));
    $('gallery-status').textContent=query?`검색 결과 ${data.total}개`:'';
    $('gallery-empty').hidden=data.total!==0;$('empty-message').textContent=query?'검색 결과가 없습니다.':'아직 생성한 표지가 없습니다.';
    $('gallery-pagination').hidden=pages<=1;$('page-number').textContent=`${page} / ${pages}`;
    $('previous-page').disabled=page<=1;$('next-page').disabled=page>=pages;
  }catch(e){if(seq===sequence){error(e.message);$('gallery-status').textContent='';}}
  finally{if(seq===sequence)$('gallery-grid').removeAttribute('aria-busy');}
}
async function openCover(id){
  const seq=++detailSequence;$('gallery-error').hidden=true;
  try{
    const item=await Keycover.json(`api.php?action=cover&id=${encodeURIComponent(id)}`);
    if(seq!==detailSequence)return;detail=item;
    $('detail-title').textContent=item.title;$('detail-image').src=fileUrl(id);$('detail-image').alt=item.title;
    $('detail-image').style.backgroundColor=item.options.transparent_background?Keycover.previewColor(item.options):'';
    $('detail-summary').textContent=item.summary;$('detail-direction').textContent=item.visual_direction;
    $('detail-delete').hidden=!item.can_manage;
    $('detail-error').hidden=true;$('copy-status').textContent='';

    const imageShare=item.options.image_share;
    const spaces={left:'왼쪽',right:'오른쪽',top:'위쪽',none:imageShare?'주변':'여백 없이'},moods={light:'밝고 부드럽게',dark:'깊고 차분하게',color:'컬러풀하게'};
    const values=[['생성일',date(item.created_at)],['이미지',`${item.width} × ${item.height} · PNG · ${size(item.bytes)}`],item.source_type==='text'?['입력 방식','간단한 설명']:['원본 PDF',`${item.filename} · ${item.pages}페이지`],['이미지 비중',imageShare?`${imageShare}% · 글자 여백 ${100-imageShare}%`:'설정 기록 없음'],['제목 자리',spaces[item.options.space]],['배경과 빛',moods[item.options.mood]],['모델',item.model],['추가 요청',item.options.notes||'없음']];
    if(item.options.mood==='color'&&item.options.background_color)values.splice(6,0,['배경색',item.options.background_color]);
    if(item.options.color_theme)values.splice(3,0,['컬러 테마',({blue:'블루',ocean:'오션',green:'그린',sunset:'선셋',berry:'베리',mono:'모노'})[item.options.color_theme]||item.options.color_theme]);
    if(item.options.complexity)values.splice(3,0,['복잡도',({high:'상',medium:'중',low:'하'})[item.options.complexity]]);
    if(item.options.transparent_background)values.splice(3,0,['배경','투명 PNG']);
    $('detail-meta').replaceChildren(...values.map(([label,value])=>{const div=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;dd.textContent=value;div.append(dt,dd);return div;}));
    $('detail-keywords').replaceChildren(...item.keywords.map(keyword=>{const span=document.createElement('span');span.textContent=keyword;return span;}));
    $('prompt-title').textContent=item.prompt_source==='exact'?'생성 프롬프트':'생성 프롬프트 · 기존 설정으로 복원';
    $('detail-prompt').value=item.prompt;$('resume-job').hidden=!item.can_resume;$('resume-job').href=`./?job=${item.job_id}`;
    updateUrl(id);if(!$('cover-dialog').open)$('cover-dialog').showModal();$('cover-dialog').scrollTop=0;
  }catch(e){if(seq===detailSequence){error(e.message);updateUrl();}}
}
$('gallery-search').addEventListener('submit',e=>{e.preventDefault();query=$('search-input').value.trim();page=1;updateUrl();load();});
$('clear-search').addEventListener('click',()=>{$('search-input').value='';query='';page=1;updateUrl();load();});
$('previous-page').addEventListener('click',()=>{if(page>1){page--;load();}});
$('next-page').addEventListener('click',()=>{if(page<pages){page++;load();}});
$('retry-gallery').addEventListener('click',load);
$('close-detail').addEventListener('click',()=>$('cover-dialog').close());
$('cover-dialog').addEventListener('close',()=>{detailSequence++;detail=null;updateUrl();});
$('cover-dialog').addEventListener('click',e=>{if(e.target===$('cover-dialog')){const r=$('cover-dialog').getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)$('cover-dialog').close();}});
$('detail-download').addEventListener('click',()=>{if(detail)download(detail,$('detail-download'),message=>{$('detail-error').textContent=message;$('detail-error').hidden=false;});});
$('copy-prompt').addEventListener('click',async()=>{
  if(!detail)return;
  try{await navigator.clipboard.writeText(detail.prompt);$('copy-status').textContent='복사했습니다.';}
  catch{$('detail-prompt').focus();$('detail-prompt').select();$('copy-status').textContent='프롬프트를 선택했습니다. 복사해 주세요.';}
});
async function changeVisibility(action,id){
  const session=await Keycover.json('api.php?action=session');
  const response=await fetch(`api.php?action=${action}`,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':session.csrf},body:JSON.stringify({id})});
  const data=await response.json();if(!response.ok)throw new Error(data.error||'공개 설정을 변경하지 못했습니다.');
}
$('detail-delete').addEventListener('click',async()=>{
  if(!detail?.can_manage||!confirm('이 표지를 공개 갤러리에서 숨길까요?'))return;
  const id=detail.id;$('detail-delete').disabled=true;
  try{await changeVisibility('delete-cover',id);lastHidden=id;$('cover-dialog').close();await load();$('delete-notice').hidden=false;}
  catch(e){error(e.message);}finally{$('detail-delete').disabled=false;}
});
$('undo-delete').addEventListener('click',async()=>{
  if(!lastHidden)return;$('undo-delete').disabled=true;
  try{await changeVisibility('restore-cover',lastHidden);lastHidden=null;$('delete-notice').hidden=true;await load();}
  catch(e){error(e.message);}finally{$('undo-delete').disabled=false;}
});
Keycover.start({
  public:true,
  async init(){const params=new URLSearchParams(location.search);query=params.get('q')||'';page=Math.max(1,Number(params.get('page'))||1);$('search-input').value=query;await load();const id=params.get('cover');if(id)await openCover(id);},
  error
});
