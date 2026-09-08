import React, {useLayoutEffect, useRef} from 'react';
import {createRoot} from 'react-dom/client';
import icons from 'selected-icons';

// Static CSS uses the same package's server-rendered SVGs. React mounts only
// inside an icon on explicit interaction, never around links or page content.
const reduced = matchMedia('(prefers-reduced-motion: reduce)');
const active = new Map();
const triggerSelector = 'a,button,summary,[role="button"]';
function nameFor(el) {
  return [...el.classList].find(c=>c.startsWith('bi-') && icons[c.slice(3)])?.slice(3);
}
function AnimatedGlyph({Icon, host, size}) {
  const ref = useRef(null);
  useLayoutEffect(()=>{
    host.classList.add('animateicons-playing');
    ref.current?.startAnimation();
    return ()=>host.classList.remove('animateicons-playing');
  },[host]);
  return <Icon ref={ref} size={size} duration={0.75} isAnimated={false} aria-hidden="true" />;
}
function stop(el) {
  const entry=active.get(el);
  if (!entry) return;
  entry.root.unmount();
  entry.mount.remove();
  active.delete(el);
}
function start(control) {
  if (reduced.matches || control.matches(':disabled,[aria-disabled="true"]')) return;
  control.querySelectorAll('.bi').forEach(el=>{
    const name=nameFor(el);
    if(!name || active.has(el)) return;
    const box=el.getBoundingClientRect();
    if(!box.width || !box.height) return;
    const mount=document.createElement('span');
    mount.className='animateicons-mount';
    mount.setAttribute('aria-hidden','true');
    el.append(mount);
    const root=createRoot(mount);
    active.set(el,{root,mount,name});
    root.render(<AnimatedGlyph Icon={icons[name]} host={el} size={Math.min(box.width,box.height)} />);
  });
}
function enter(event) {
  const control=event.target instanceof Element && event.target.closest(triggerSelector);
  if(!control || (event.relatedTarget instanceof Node && control.contains(event.relatedTarget)))return;
  start(control);
}
function leave(event) {
  const control=event.target instanceof Element && event.target.closest(triggerSelector);
  if(!control || (event.relatedTarget instanceof Node && control.contains(event.relatedTarget)))return;
  if(control.matches(':hover') || control.contains(document.activeElement))return;
  control.querySelectorAll('.bi').forEach(stop);
}
document.addEventListener('pointerover',enter);
document.addEventListener('pointerout',leave);
document.addEventListener('focusin',enter);
document.addEventListener('focusout',leave);
// Widget views replace nodes and update icon classes after API responses.
new MutationObserver(()=>{
  for(const [el,entry] of active) if(!el.isConnected || !entry.mount.isConnected || nameFor(el)!==entry.name) stop(el);
}).observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['class']});
reduced.addEventListener('change',()=>{if(reduced.matches)[...active.keys()].forEach(stop);});
window.addEventListener('pagehide',()=>[...active.keys()].forEach(stop));
