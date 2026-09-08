(() => {
    'use strict';

    const script = document.currentScript;
    if (!script || document.getElementById('eambassador-widget-root')) return;
    const widgetUrl = script.dataset.widgetUrl || new URL('../../widget', script.src).href;
    const font = new FontFace('Inter', `url("${new URL('../fonts/inter/InterVariable.woff2', script.src).href}")`, {weight: '100 900', display: 'swap'});
    font.load().then(loaded => document.fonts.add(loaded)).catch(() => {});
    const position = script.dataset.position === 'left' ? 'left' : 'right';
    const label = script.dataset.label || 'Hỏi đại sứ CMC';
    const host = document.createElement('div');
    host.id = 'eambassador-widget-root';
    document.body.appendChild(host);
    const root = host.attachShadow({ mode: 'open' });
    root.innerHTML = `
        <style>
            :host { all: initial; }
            .launcher { position: fixed; ${position}: 24px; bottom: 24px; z-index: 2147483000; display: flex; align-items: center; gap: 10px; min-height: 54px; padding: 0 18px 0 13px; color: #fff; background: #008fd5; border: 0; border-radius: 16px; box-shadow: 0 15px 34px rgba(0,39,87,.24); cursor: pointer; font: 700 14px/1.2 Inter, sans-serif; transition: transform .18s ease, background .18s ease; }
            .launcher:hover { background: #006eaa; transform: translateY(-2px); }
            .launcher:focus-visible { outline: 3px solid rgba(0,143,213,.3); outline-offset: 3px; }
            .launcher-icon { position: relative; display: grid; width: 34px; height: 34px; place-items: center; color: #008fd5; background: #fff; border-radius: 10px; }
            .launcher-icon::after { position: absolute; top: -2px; right: -2px; width: 9px; height: 9px; content: ''; background: #14725b; border: 2px solid #008fd5; border-radius: 50%; }
            .launcher-icon svg { width: 22px; height: 22px; fill: currentColor; }
            @media (prefers-reduced-motion: no-preference) and (hover: hover) { .launcher-icon svg { transition: transform .2s cubic-bezier(.2,.8,.2,1); } .launcher:hover .launcher-icon svg { transform: rotate(-7deg) scale(1.08); } .launcher:active .launcher-icon svg { transform: scale(.94); } }
            .panel { position: fixed; ${position}: 24px; bottom: 90px; z-index: 2147483001; width: min(430px, calc(100vw - 32px)); height: min(720px, calc(100vh - 116px)); overflow: hidden; background: #fff; border-radius: 18px; box-shadow: 0 28px 72px rgba(0,39,87,.28); opacity: 0; transform: translateY(18px) scale(.98); visibility: hidden; transition: opacity .2s ease, transform .24s cubic-bezier(.16,1,.3,1), visibility .2s; }
            .panel.open { opacity: 1; transform: none; visibility: visible; }
            iframe { width: 100%; height: 100%; border: 0; }
            @media (max-width: 520px) { .launcher { ${position}: 14px; bottom: 14px; } .panel { inset: 0; width: 100vw; height: 100vh; border-radius: 0; } }
            @media (prefers-reduced-motion: reduce) { .launcher, .panel { transition: none; } }
        </style>
        <button class="launcher" type="button" aria-expanded="false" aria-controls="eambassador-panel">
            <!-- Phosphor Icons 2.1.1, chat-circle-dots-duotone (MIT; assets/icons/LICENSE). -->
            <span class="launcher-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M224,128A96,96,0,0,1,79.93,211.11h0L42.54,223.58a8,8,0,0,1-10.12-10.12l12.47-37.39h0A96,96,0,1,1,224,128Z" opacity="0.2"/><path d="M128,24A104,104,0,0,0,36.18,176.88L24.83,210.93a16,16,0,0,0,20.24,20.24l34.05-11.35A104,104,0,1,0,128,24Zm0,192a87.87,87.87,0,0,1-44.06-11.81,8,8,0,0,0-4-1.08,7.85,7.85,0,0,0-2.53.42L40,216,52.47,178.6a8,8,0,0,0-.66-6.54A88,88,0,1,1,128,216Zm12-88a12,12,0,1,1-12-12A12,12,0,0,1,140,128Zm-44,0a12,12,0,1,1-12-12A12,12,0,0,1,96,128Zm88,0a12,12,0,1,1-12-12A12,12,0,0,1,184,128Z"/></svg></span>
            <span>${label.replace(/[<>&"']/g, '')}</span>
        </button>
        <section class="panel" id="eambassador-panel" aria-label="Tư vấn cùng đại sứ CMC">
            <iframe title="Tư vấn cùng đại sứ sinh viên CMC" loading="lazy" allow="clipboard-write"></iframe>
        </section>`;
    const launcher = root.querySelector('.launcher');
    const panel = root.querySelector('.panel');
    const frame = root.querySelector('iframe');
    let loaded = false;
    const setOpen = (open) => {
        if (open && !loaded) {
            frame.src = widgetUrl;
            loaded = true;
        }
        panel.classList.toggle('open', open);
        launcher.setAttribute('aria-expanded', String(open));
        launcher.setAttribute('aria-label', open ? 'Đóng tư vấn đại sứ' : label);
    };
    launcher.addEventListener('click', () => setOpen(!panel.classList.contains('open')));
    window.addEventListener('message', (event) => {
        if (event.source === frame.contentWindow && event.data?.type === 'eambassador:close') setOpen(false);
    });
})();
