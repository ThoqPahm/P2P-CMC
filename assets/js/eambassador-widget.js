(() => {
    'use strict';

    const script = document.currentScript;
    if (!script || document.getElementById('eambassador-widget-root')) return;
    const widgetUrl = script.dataset.widgetUrl || new URL('../../widget', script.src).href;
    const position = script.dataset.position === 'left' ? 'left' : 'right';
    const label = script.dataset.label || 'Hỏi đại sứ CMC';
    const host = document.createElement('div');
    host.id = 'eambassador-widget-root';
    document.body.appendChild(host);
    const root = host.attachShadow({ mode: 'open' });
    root.innerHTML = `
        <style>
            :host { all: initial; }
            .launcher { position: fixed; ${position}: 24px; bottom: 24px; z-index: 2147483000; display: flex; align-items: center; gap: 10px; min-height: 54px; padding: 0 18px 0 13px; color: #fff; background: #008fd5; border: 0; border-radius: 16px; box-shadow: 0 15px 34px rgba(0,39,87,.24); cursor: pointer; font: 700 14px/1.2 "Segoe UI Variable", Aptos, "Segoe UI", sans-serif; transition: transform .18s ease, background .18s ease; }
            .launcher:hover { background: #006eaa; transform: translateY(-2px); }
            .launcher:focus-visible { outline: 3px solid rgba(0,143,213,.3); outline-offset: 3px; }
            .launcher-icon { position: relative; display: grid; width: 34px; height: 34px; place-items: center; color: #008fd5; background: #fff; border-radius: 10px; }
            .launcher-icon::after { position: absolute; top: -2px; right: -2px; width: 9px; height: 9px; content: ''; background: #14725b; border: 2px solid #008fd5; border-radius: 50%; }
            .launcher-icon svg { width: 19px; height: 19px; fill: currentColor; }
            .panel { position: fixed; ${position}: 24px; bottom: 90px; z-index: 2147483001; width: min(430px, calc(100vw - 32px)); height: min(720px, calc(100vh - 116px)); overflow: hidden; background: #fff; border-radius: 18px; box-shadow: 0 28px 72px rgba(0,39,87,.28); opacity: 0; transform: translateY(18px) scale(.98); visibility: hidden; transition: opacity .2s ease, transform .24s cubic-bezier(.16,1,.3,1), visibility .2s; }
            .panel.open { opacity: 1; transform: none; visibility: visible; }
            iframe { width: 100%; height: 100%; border: 0; }
            @media (max-width: 520px) { .launcher { ${position}: 14px; bottom: 14px; } .panel { inset: 0; width: 100vw; height: 100vh; border-radius: 0; } }
            @media (prefers-reduced-motion: reduce) { .launcher, .panel { transition: none; } }
        </style>
        <button class="launcher" type="button" aria-expanded="false" aria-controls="eambassador-panel">
            <span class="launcher-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 5a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm5 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm5 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z"/></svg></span>
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
