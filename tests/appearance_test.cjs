const {readFileSync}=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const source=readFileSync('public/assets/appearance.js','utf8');
function page({saved=null,dark=false,blocked=false}={}){
  const events={},mediaEvents={},clicks={},root={dataset:{}},meta={},button={setAttribute(k,v){this[k]=v;}};
  const media={matches:dark,addEventListener(k,fn){mediaEvents[k]=fn;}};
  button.addEventListener=(k,fn)=>clicks[k]=fn;
  const storage={value:saved,getItem(){if(blocked)throw Error('denied');return this.value;},setItem(k,v){if(blocked)throw Error('denied');this.value=v;}};
  const document={documentElement:root,querySelector:()=>meta,getElementById:()=>button,addEventListener(k,fn){events[k]=fn;}};
  const window={matchMedia:()=>media,addEventListener(k,fn){events[k]=fn;}};
  vm.runInNewContext(source,{document,window,localStorage:storage});
  const initial=root.dataset.appearance;events.DOMContentLoaded();
  return {root,meta,button,storage,initial,click:()=>clicks.click(),system(d){media.matches=d;mediaEvents.change();},sync(value,key='keycover.appearance'){events.storage({key,newValue:value});}};
}
let p=page({dark:true});assert.equal(p.initial,'dark');assert.equal(p.meta.content,'#14151c');
p.system(false);assert.equal(p.root.dataset.appearance,'light');p.click();assert.equal(p.root.dataset.appearance,'dark');assert.equal(p.storage.value,'dark');assert.equal(p.button['aria-label'],'라이트 모드로 전환');p.system(false);assert.equal(p.root.dataset.appearance,'dark');
let next=page({saved:p.storage.value});assert.equal(next.initial,'dark');next.click();assert.equal(next.storage.value,'light');assert.equal(next.button['aria-label'],'다크 모드로 전환');
p.sync('light');assert.equal(p.root.dataset.appearance,'light');p.sync('dark','unrelated');assert.equal(p.root.dataset.appearance,'light');p.sync(null);p.system(true);assert.equal(p.root.dataset.appearance,'dark');
p=page({saved:'unexpected',dark:true});assert.equal(p.initial,'dark');
p=page({dark:false,blocked:true});p.click();assert.equal(p.root.dataset.appearance,'dark');p.click();assert.equal(p.root.dataset.appearance,'light');
console.log('PASS: first-paint system mode, system changes, explicit override, persistence, cross-tab sync, invalid preference and blocked storage');
