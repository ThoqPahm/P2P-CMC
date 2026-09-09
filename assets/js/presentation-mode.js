(() => {
    'use strict';

    const config = window.CMC_PRESENTATION || {};
    const frame = document.querySelector('#presentationFrame');
    const transition = document.querySelector('#presentationTransition');
    const contextRole = document.querySelector('#presentationContextRole');
    const contextDestination = document.querySelector('#presentationContextDestination');
    const stepLabel = document.querySelector('#presentationStep');
    const titleLabel = document.querySelector('#presentationTitle');
    const previous = document.querySelector('#presentationPrevious');
    const next = document.querySelector('#presentationNext');
    const fullscreen = document.querySelector('#presentationFullscreen');
    const overviewButton = document.querySelector('#presentationOverview');
    const overview = document.querySelector('#presentationOverviewDialog');
    const overviewClose = document.querySelector('#presentationOverviewClose');
    const sceneList = document.querySelector('#presentationSceneList');
    let current = 0;
    let changing = false;
    let runVersion = 0;
    let manualMode = false;
    let queuedDirection = 0;
    let fastForward = false;
    const activeDelays = new Set();
    const MANUAL_ABORT = Symbol('manual-control');

    const scenes = [
        {
            target: 'admin-dashboard',
            role: 'Cán bộ nhà trường',
            destination: 'Tổng quan quản trị',
            title: 'Nhà trường định hướng chủ đề',
            cue: 'Nhà trường tạo chủ đề và brief để trải nghiệm sinh viên được tổ chức theo nhu cầu học sinh.',
            action: showAdminCampaign,
        },
        {
            target: 'student-dashboard',
            role: 'Đại sứ sinh viên',
            destination: 'Tổng quan sinh viên',
            title: 'Đại sứ nhận nhiệm vụ và gửi nội dung',
            cue: 'Đại sứ xem yêu cầu, kể trải nghiệm cá nhân và gửi bài để rà soát.',
            action: showAmbassadorCampaign,
        },
        {
            target: 'widget',
            role: 'Học sinh THPT',
            destination: 'Widget tư vấn trên website trường',
            title: 'Nội dung tiếp cận học sinh',
            cue: 'Sau khi được duyệt, bài chia sẻ xuất hiện tại điểm chạm công khai của học sinh.',
            action: showPublishedContent,
        },
        {
            target: 'widget',
            continueFromPrevious: true,
            title: 'Học sinh thu hẹp nhu cầu bằng bộ lọc',
            cue: 'Học sinh dùng ngành, quê quán, khóa và từ khóa để thu hẹp danh sách đại sứ đã xác minh.',
            action: showAmbassadorFilters,
        },
        {
            target: 'widget',
            continueFromPrevious: true,
            title: 'Học sinh chọn đại sứ phù hợp',
            cue: 'Sau khi lọc theo ngành, học sinh xem hồ sơ và chọn người có trải nghiệm liên quan.',
            action: selectAmbassadorProfile,
        },
        {
            target: 'widget',
            continueFromPrevious: true,
            title: 'Đại sứ chia sẻ trải nghiệm cá nhân',
            cue: 'Học sinh hỏi về mức độ phù hợp; đại sứ trả lời từ trải nghiệm học tập của mình.',
            action: playProspectExperience,
        },
        {
            target: 'widget',
            continueFromPrevious: true,
            title: 'Câu hỏi học bổng cần được xác nhận',
            cue: 'Khi học sinh hỏi về học bổng, cuộc trò chuyện chạm tới giới hạn thẩm quyền của đại sứ.',
            action: playScholarshipQuestion,
        },
        {
            target: 'student-dashboard',
            stage: 'ambassador-handoff',
            role: 'Đại sứ sinh viên',
            destination: 'Tổng quan sinh viên',
            title: 'Đại sứ chuyển câu hỏi cần xác nhận',
            cue: 'Đại sứ không tự kết luận về học bổng mà chuyển nguyên câu hỏi đến Ban Tuyển sinh.',
            action: playAmbassadorHandoff,
        },
        {
            target: 'admin-dashboard',
            role: 'Cán bộ nhà trường',
            destination: 'Tổng quan quản trị',
            title: 'Nhà trường tiếp nhận và xác nhận thông tin',
            cue: 'Cán bộ tiếp nhận câu hỏi, kiểm tra nguồn và chịu trách nhiệm với thông tin chính thức.',
            action: showAdminReview,
        },
        {
            target: 'admin-moderation',
            stage: 'admin-review',
            continueFromPrevious: true,
            title: 'Phản hồi quay lại cải thiện truyền thông',
            cue: 'Những câu hỏi lặp lại trở thành đầu vào cho chủ đề và chiến dịch tiếp theo, khép vòng Outbound - Inbound.',
            action: showClosingLoop,
        },
    ];

    const delay = (milliseconds) => {
        if (fastForward) return Promise.resolve();
        return new Promise((resolve) => {
            let settled = false;
            let timeoutId;
            const finish = () => {
                if (settled) return;
                settled = true;
                window.clearTimeout(timeoutId);
                activeDelays.delete(finish);
                resolve();
            };
            timeoutId = window.setTimeout(finish, milliseconds);
            activeDelays.add(finish);
        });
    };

    const fastForwardCurrentScene = () => {
        fastForward = true;
        [...activeDelays].forEach((finish) => finish());
    };
    const doc = () => frame.contentDocument;
    const assertAutomating = () => { if (manualMode) throw MANUAL_ABORT; };

    const matchingText = (selector, text, root = doc()) => [...root.querySelectorAll(selector)].find((element) => element.textContent.includes(text));

    const installStage = () => {
        const document = doc();
        if (!document || document.querySelector('#presentationPointer')) return;
        const style = document.createElement('style');
        style.textContent = `
            html { scroll-behavior: smooth; }
            #presentationPointer { position: fixed; z-index: 99999; width: 18px; height: 24px; left: 50%; top: 50%; pointer-events: none; opacity: 0; transition: left .62s cubic-bezier(.22,.8,.3,1), top .62s cubic-bezier(.22,.8,.3,1), opacity .18s ease; filter: drop-shadow(0 2px 2px rgba(0,39,87,.25)); }
            #presentationPointer::before { content: ''; position: absolute; inset: 0; background: #002757; clip-path: polygon(0 0, 0 86%, 29% 65%, 48% 100%, 62% 93%, 44% 59%, 76% 58%); }
            #presentationPointer::after { content: ''; position: absolute; width: 8px; height: 8px; border: 2px solid #00a8d8; border-radius: 50%; left: -3px; top: -3px; opacity: 0; }
            #presentationPointer.is-visible { opacity: 1; }
            #presentationPointer.is-clicking::after { animation: presentClick .48s ease-out; }
            .presentation-focus { position: relative; z-index: 2; box-shadow: 0 0 0 3px rgba(0,143,213,.38), 0 15px 44px rgba(0,39,87,.12) !important; transition: box-shadow .25s ease; }
            .presentation-typed { caret-color: #008fd5; }
            body.presentation-sidebar-nav .app-sidebar { visibility: hidden !important; transform: translateX(-102%) !important; transition: transform .22s ease, visibility 0s linear .22s !important; }
            body.presentation-sidebar-nav .app-sidebar.open { visibility: visible !important; transform: translateX(0) !important; transition-delay: 0s !important; }
            body.presentation-sidebar-nav .sidebar-overlay.open { position: fixed; z-index: 1035; inset: 0; display: block; background: rgba(0,39,87,.52); }
            body.presentation-sidebar-nav .app-main { margin-left: 0 !important; }
            body.presentation-sidebar-nav #sidebarToggle { display: inline-flex !important; }
            @keyframes presentClick { 0% { opacity: .95; transform: scale(.4); } 100% { opacity: 0; transform: scale(3.2); } }
        `;
        document.head.appendChild(style);
        const pointer = document.createElement('span');
        pointer.id = 'presentationPointer';
        pointer.setAttribute('aria-hidden', 'true');
        document.body.appendChild(pointer);
        if (document.querySelector('#appSidebar')) document.body.classList.add('presentation-sidebar-nav');
        document.addEventListener('keydown', handleKeys);
        const takeManualControl = (event) => {
            if (!event.isTrusted) return;
            manualMode = true;
            runVersion += 1;
            changing = false;
            previous.disabled = current === 0;
            next.disabled = false;
        };
        document.addEventListener('pointerdown', takeManualControl, true);
        document.addEventListener('input', takeManualControl, true);
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof frame.contentWindow.HTMLFormElement)) return;
            const action = new URL(form.action || frame.contentWindow.location.href, frame.contentWindow.location.href);
            const filename = action.pathname.split('/').pop();
            if (!['actions.php', 'program-actions.php'].includes(filename)) return;
            event.preventDefault();
            takeManualControl(event);
            const entries = [...new FormData(form).entries()].filter(([, value]) => typeof value === 'string');
            if (event.submitter?.name) entries.push([event.submitter.name, event.submitter.value]);
            void runPresentationAction(action.href, entries).catch((error) => console.error(error));
        }, true);
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a');
            if (!link || link.dataset.presentationAutomated === 'true') {
                if (link) event.preventDefault();
                return;
            }
            const href = link.getAttribute('href') || '';
            if (href.startsWith('#') || link.target === '_blank' || /^(?:mailto:|tel:|javascript:)/i.test(href)) return;
            const url = new URL(link.href, frame.contentWindow.location.href);
            if (url.origin !== window.location.origin) return;
            event.preventDefault();
            void navigateManualHref(url.href).catch((error) => console.error(error));
        }, true);

        const nativeFetch = frame.contentWindow.fetch.bind(frame.contentWindow);
        frame.contentWindow.fetch = (input, options) => {
            const rawUrl = typeof input === 'string' || input instanceof URL ? String(input) : input?.url;
            if (!rawUrl) return nativeFetch(input, options);
            const url = new URL(rawUrl, frame.contentWindow.location.href);
            if (url.origin === window.location.origin && url.pathname.endsWith('/api.php')) {
                const proxy = new URL(config.api, window.location.href);
                url.searchParams.forEach((value, key) => proxy.searchParams.append(key, value));
                proxy.searchParams.set('token', config.token);
                return nativeFetch(proxy.href, options);
            }
            return nativeFetch(input, options);
        };
    };

    const pointTo = async (element, click = false) => {
        if (!element) return;
        assertAutomating();
        doc().querySelectorAll('.presentation-focus').forEach((item) => item.classList.remove('presentation-focus'));
        element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        await delay(420);
        assertAutomating();
        const pointer = doc().querySelector('#presentationPointer');
        const rect = element.getBoundingClientRect();
        pointer.style.left = `${Math.max(4, Math.min(frame.clientWidth - 22, rect.left + Math.min(rect.width * .62, rect.width - 8)))}px`;
        pointer.style.top = `${Math.max(4, Math.min(frame.clientHeight - 28, rect.top + Math.min(rect.height * .55, rect.height - 8)))}px`;
        pointer.classList.add('is-visible');
        element.classList.add('presentation-focus');
        await delay(690);
        assertAutomating();
        if (click) {
            pointer.classList.remove('is-clicking');
            void pointer.offsetWidth;
            pointer.classList.add('is-clicking');
            element.dataset.presentationAutomated = 'true';
            element.click();
            delete element.dataset.presentationAutomated;
            await delay(450);
            assertAutomating();
            element.blur?.();
        }
        element.classList.remove('presentation-focus');
    };

    const typeInto = async (element, value, speed = 16) => {
        if (!element) return;
        assertAutomating();
        element.focus();
        element.value = '';
        element.classList.add('presentation-typed');
        for (const character of Array.from(value)) {
            assertAutomating();
            element.value += character;
            element.dispatchEvent(new Event('input', { bubbles: true }));
            await delay(speed);
        }
        element.blur();
    };

    const typeText = async (element, value, speed = 12) => {
        element.textContent = '';
        for (const character of Array.from(value)) {
            assertAutomating();
            element.textContent += character;
            await delay(speed);
        }
    };

    const visualClick = async (element) => {
        if (!element) return;
        const pointer = doc().querySelector('#presentationPointer');
        pointer.classList.remove('is-clicking');
        void pointer.offsetWidth;
        pointer.classList.add('is-clicking');
        await delay(360);
    };

    async function showAdminCampaign() {
        await navigateSidebar('Chiến dịch', 'admin-campaigns');
        const cardTitle = matchingText('.campaign-card h3', 'Một ngày đi học ngành Digital Marketing');
        const card = cardTitle?.closest('.campaign-card');
        const detail = card?.querySelector('summary');
        await pointTo(cardTitle || card);
        await pointTo(detail, true);
    }

    async function showAmbassadorCampaign() {
        await navigateSidebar('Chiến dịch nội dung', 'campaigns');
        const cardTitle = matchingText('.mission-card h3', 'Một ngày đi học ngành Digital Marketing');
        const card = cardTitle?.closest('.mission-card');
        const submit = card?.querySelector('[data-bs-toggle="modal"]');
        await pointTo(cardTitle || card);
        await pointTo(submit, true);
        await delay(380);
        const modal = doc().querySelector('.modal.show') || doc().querySelector(submit?.dataset.bsTarget || '');
        await typeInto(modal?.querySelector('input[name="content_url"]'), 'https://video.example/mot-ngay-hoc-digital-marketing', 8);
        await typeInto(modal?.querySelector('textarea[name="caption"]'), 'Một ngày học Digital Marketing dưới góc nhìn sinh viên.', 11);
        await pointTo(modal?.querySelector('button[type="submit"]'));
    }

    async function showPublishedContent() {
        const contentTab = doc().querySelector('[data-widget-tab="content"]');
        await pointTo(contentTab, true);
        await delay(450);
        const title = matchingText('.content-card-title', 'Một ngày học Digital Marketing');
        await pointTo(title?.closest('.content-card'), true);
    }

    async function showAmbassadorFilters() {
        const back = doc().querySelector('#widgetBack:not(.is-hidden)');
        if (back) await pointTo(back, true);
        const ambassadorsTab = doc().querySelector('[data-availability="all"]');
        await pointTo(ambassadorsTab, true);
        await delay(350);
        const search = doc().querySelector('#widgetSearch');
        const major = doc().querySelector('#majorFilter');
        const hometown = doc().querySelector('#hometownFilter');
        const year = doc().querySelector('#yearFilter');
        await pointTo(search);
        await pointTo(hometown);
        await pointTo(year);
        await pointTo(major);
    }

    async function selectAmbassadorProfile() {
        const major = doc().querySelector('#majorFilter');
        await pointTo(major);
        major.value = 'Digital Marketing';
        major.dispatchEvent(new Event('input', { bubbles: true }));
        await delay(600);
        const card = matchingText('.widget-ambassador', 'Trần Minh Anh');
        await pointTo(card, true);
        await delay(450);
        await pointTo(doc().querySelector('#profileName'));
    }

    async function openMinhAnhChat() {
        if (!doc().querySelector('#chatView')?.classList.contains('is-hidden')) return;
        if (doc().querySelector('#profileName')?.textContent.trim() !== 'Trần Minh Anh' || doc().querySelector('#profileView')?.classList.contains('is-hidden')) {
            const major = doc().querySelector('#majorFilter');
            major.value = 'Digital Marketing';
            major.dispatchEvent(new Event('input', { bubbles: true }));
            await delay(300);
            await pointTo(matchingText('.widget-ambassador', 'Trần Minh Anh'), true);
            await delay(350);
        }
        await pointTo(doc().querySelector('#openChatForm'), true);
        await delay(350);
    }

    const addChatBubble = async (list, className, content, author, animate = true) => {
        const bubble = doc().createElement('div');
        bubble.className = `widget-message ${className}`;
        const paragraph = doc().createElement('p');
        const small = doc().createElement('small');
        small.textContent = author;
        bubble.append(paragraph, small);
        list.appendChild(bubble);
        if (animate) await typeText(paragraph, content, className ? 10 : 7);
        else paragraph.textContent = content;
        list.scrollTop = list.scrollHeight;
        if (animate) await delay(280);
        return bubble;
    };

    const seedExperienceMessages = async (list) => {
        list.innerHTML = '';
        await addChatBubble(list, 'mine', 'Em là người hướng nội, liệu học Digital Marketing có phù hợp không ạ?', 'Bạn', false);
        await addChatBubble(list, '', 'Có em nhé. Ngành có phần phân tích số liệu và nhiều bài tập nhóm, nhưng người hướng nội vẫn có lợi thế ở khả năng quan sát, lắng nghe và chuẩn bị nội dung kỹ.', 'Trần Minh Anh', false);
    };

    async function playProspectExperience() {
        await openMinhAnhChat();
        const list = doc().querySelector('#widgetMessages');
        if (!list) return;
        list.innerHTML = '';
        const input = doc().querySelector('#widgetMessageInput');
        const send = doc().querySelector('#widgetMessageForm button[type="submit"]');
        const messages = [
            ['mine', 'Em là người hướng nội, liệu học Digital Marketing có phù hợp không ạ?', 'Bạn'],
            ['', 'Có em nhé. Ngành có phần phân tích số liệu và nhiều bài tập nhóm, nhưng người hướng nội vẫn có lợi thế ở khả năng quan sát, lắng nghe và chuẩn bị nội dung kỹ.', 'Trần Minh Anh'],
        ];
        for (const [className, content, author] of messages) {
            if (className) {
                await pointTo(input);
                await typeInto(input, content, 9);
                await pointTo(send);
                await visualClick(send);
                input.value = '';
            } else {
                const typing = doc().createElement('div');
                typing.className = 'widget-message';
                typing.innerHTML = '<p>Đang trả lời…</p><small>Trần Minh Anh</small>';
                list.appendChild(typing);
                list.scrollTop = list.scrollHeight;
                await delay(650);
                typing.remove();
            }
            await addChatBubble(list, className, content, author);
        }
        const latest = list.lastElementChild;
        await pointTo(latest);
    }

    async function playScholarshipQuestion() {
        await openMinhAnhChat();
        const list = doc().querySelector('#widgetMessages');
        if (!list) return;
        if (!list.textContent.includes('người hướng nội')) await seedExperienceMessages(list);
        const input = doc().querySelector('#widgetMessageInput');
        const send = doc().querySelector('#widgetMessageForm button[type="submit"]');
        const question = 'Với kết quả học tập hiện tại, em có chắc chắn nhận được học bổng không ạ?';
        await pointTo(input);
        await typeInto(input, question, 9);
        await pointTo(send);
        await visualClick(send);
        input.value = '';
        const bubble = await addChatBubble(list, 'mine', question, 'Bạn');
        await pointTo(bubble);
    }

    async function injectInboxMessages() {
        const list = doc().querySelector('#messageList');
        if (!list) return;
        list.innerHTML = `
            <div class="message participant"><p>Em là người hướng nội, liệu học Digital Marketing có phù hợp không ạ?</p><small>Mai Thu</small></div>
            <div class="message mine"><p>Có em nhé. Người hướng nội vẫn có lợi thế ở khả năng quan sát, lắng nghe và chuẩn bị nội dung kỹ.</p><small>Trần Minh Anh</small></div>
            <div class="message participant"><p>Với kết quả học tập hiện tại, em có chắc chắn nhận được học bổng không ạ?</p><small>Mai Thu</small></div>`;
    }

    async function playAmbassadorHandoff() {
        await navigateSidebar('Hộp thư tư vấn', 'inbox', 'ambassador-handoff');
        await injectInboxMessages();
        await pointTo(matchingText('.inbox-row', 'Mai Thu'), true);
        await delay(300);
        await pointTo(doc().querySelector('.inbox-room-head'));
        const handoff = doc().querySelector('[data-bs-target="#escalateModal"]');
        await pointTo(handoff, true);
        await delay(350);
        const reason = doc().querySelector('#escalateReason');
        await typeInto(reason, 'Học sinh hỏi: Với kết quả học tập hiện tại, em có chắc chắn nhận được học bổng không?', 10);
        await pointTo(doc().querySelector('#escalateModal button[type="submit"]'));
    }

    async function showAdminReview() {
        await navigateSidebar('Kiểm duyệt chat', 'admin-moderation', 'admin-review');
        await pointTo(matchingText('.moderation-conversation, a', 'Mai Thu'), true);
        await delay(300);
        const banner = doc().querySelector('.escalation-banner');
        await pointTo(banner);
        const input = banner?.querySelector('input[name="official_answer"]');
        await typeInto(input, 'Ban Tuyển sinh sẽ đối chiếu hồ sơ và điều kiện của từng chương trình trước khi xác nhận.', 10);
        await pointTo(banner?.querySelector('button[type="submit"]'));
    }

    async function showClosingLoop() {
        await navigateSidebar('Tổng quan', 'admin-dashboard');
        const campaignRoute = matchingText('.ops-route-row', 'Chiến dịch');
        await pointTo(campaignRoute);
        const action = campaignRoute?.querySelector('a');
        await pointTo(action);
    }

    const frameUrl = (scene) => {
        const parameters = new URLSearchParams({ token: config.token, target: scene.target });
        if (scene.stage) parameters.set('stage', scene.stage);
        return `${config.frame}?${parameters.toString()}`;
    };

    const loadTarget = async (target, stage = '') => {
        assertAutomating();
        transition.classList.remove('is-context');
        transition.classList.add('is-visible');
        frame.src = frameUrl({ target, stage });
        await new Promise((resolve) => frame.addEventListener('load', resolve, { once: true }));
        assertAutomating();
        installStage();
        transition.classList.remove('is-visible');
        await delay(420);
    };

    const navigateSidebar = async (label, target, stage = '') => {
        assertAutomating();
        const document = doc();
        const sidebar = document.querySelector('#appSidebar');
        const toggle = document.querySelector('#sidebarToggle');
        if (sidebar && toggle && !sidebar.classList.contains('open')) {
            await pointTo(toggle, true);
            await delay(340);
        }
        const link = matchingText('.sidebar-link', label);
        await pointTo(link, true);
        await delay(260);
        await loadTarget(target, stage);
    };

    const showContextTransition = async (scene) => {
        if (!scene.role) return;
        contextRole.textContent = scene.role;
        contextDestination.textContent = `Bắt đầu tại ${scene.destination}`;
        transition.classList.add('is-context', 'is-visible');
        await delay(1050);
    };

    const renderScene = async (index, reusePrevious = false) => {
        const version = ++runVersion;
        const scene = scenes[index];
        manualMode = false;
        changing = true;
        previous.disabled = true;
        next.disabled = true;
        stepLabel.textContent = `${index + 1} / ${scenes.length}`;
        titleLabel.textContent = scene.title;
        window.parent.postMessage({
            type: 'cmc-presentation-scene',
            index,
            step: index + 1,
            total: scenes.length,
            title: scene.title,
            phase: 'start',
        }, window.location.origin);
        next.innerHTML = index === scenes.length - 1 ? 'Kết thúc <span>✓</span>' : 'Tiếp theo <span>→</span>';
        const canReuse = reusePrevious && scene.continueFromPrevious;
        if (!canReuse) {
            await showContextTransition(scene);
            if (!scene.role) transition.classList.add('is-visible');
            frame.src = frameUrl(scene);
            await new Promise((resolve) => frame.addEventListener('load', resolve, { once: true }));
            if (version !== runVersion) return;
            installStage();
            if (scene.role) await delay(520);
            transition.classList.remove('is-visible', 'is-context');
            await delay(650);
        } else {
            await delay(350);
        }
        try {
            if (version === runVersion) await scene.action?.();
        } catch (error) {
            if (error !== MANUAL_ABORT) console.error(error);
        } finally {
            if (version === runVersion) {
                changing = false;
                previous.disabled = index === 0;
                next.disabled = false;
                window.parent.postMessage({
                    type: 'cmc-presentation-scene',
                    index,
                    step: index + 1,
                    total: scenes.length,
                    title: scene.title,
                    phase: 'ready',
                }, window.location.origin);
                const direction = queuedDirection;
                queuedDirection = 0;
                fastForward = false;
                if (direction) window.setTimeout(() => requestGo(direction), 0);
            }
        }
    };

    const visualExit = async (scene) => {
        if (!scene?.exit) return;
        const target = doc()?.querySelector(scene.exit);
        if (!target) return;
        await pointTo(target);
    };

    const go = async (direction) => {
        if (changing || overview.open) return;
        const wanted = Math.max(0, Math.min(scenes.length - 1, current + direction));
        if (wanted === current) {
            if (config.embedded) {
                window.parent.postMessage({ type: 'cmc-presentation-boundary', direction }, window.location.origin);
            }
            return;
        }
        changing = true;
        if (direction > 0) await visualExit(scenes[current]);
        current = wanted;
        await renderScene(current, direction > 0);
    };

    const requestGo = (direction) => {
        if (overview.open) return;
        if (changing) {
            queuedDirection = direction < 0 ? -1 : 1;
            fastForwardCurrentScene();
            return;
        }
        void go(direction < 0 ? -1 : 1);
    };

    const routeFromHref = (href) => {
        const url = new URL(href, window.location.href);
        const filename = url.pathname.split('/').pop();
        if (filename === 'actions.php') return { action: url.searchParams.get('action') || 'logout' };
        const pathname = url.pathname.replace(/\/+$/, '');
        const routes = Object.entries(config.routes || {}).sort((left, right) => right[1].length - left[1].length);
        for (const [page, route] of routes) {
            const marker = `/${route}`;
            const markerIndex = pathname.lastIndexOf(marker);
            if (markerIndex < 0) continue;
            const remainder = pathname.slice(markerIndex + marker.length).replace(/^\//, '');
            if (remainder && !/^\d+$/.test(remainder)) continue;
            const params = Object.fromEntries(url.searchParams.entries());
            delete params.page;
            if (remainder && ['inbox', 'admin-moderation'].includes(page)) params.conversation = remainder;
            return { page, params };
        }
        return null;
    };

    const loadPage = async (page, params = {}) => {
        transition.classList.remove('is-context');
        transition.classList.add('is-visible');
        const query = new URLSearchParams({ token: config.token, page });
        Object.entries(params).forEach(([key, value]) => query.set(key, value));
        frame.src = `${config.frame}?${query.toString()}`;
        await new Promise((resolve) => frame.addEventListener('load', resolve, { once: true }));
        installStage();
        transition.classList.remove('is-visible');
    };

    const runPresentationAction = async (href, entries = []) => {
        const original = new URL(href, window.location.href);
        const endpoint = new URL(config.action, window.location.href);
        endpoint.searchParams.set('token', config.token);
        endpoint.searchParams.set('action', original.searchParams.get('action') || '');
        endpoint.searchParams.set('source', original.pathname.endsWith('/program-actions.php') ? 'program' : 'actions');
        const body = new FormData();
        entries.forEach(([key, value]) => body.append(key, value));
        const response = await fetch(endpoint, { method: entries.length ? 'POST' : 'GET', body: entries.length ? body : undefined, credentials: 'same-origin' });
        const result = await response.json();
        if (!result.ok || !result.redirect) throw new Error(result.message || 'Không thể thực hiện thao tác trong phiên demo.');
        const route = routeFromHref(result.redirect);
        if (route?.page) await loadPage(route.page, route.params);
    };

    const navigateManualHref = async (href) => {
        const route = routeFromHref(href);
        if (route?.action) await runPresentationAction(href);
        else if (route?.page) await loadPage(route.page, route.params);
    };

    const handleKeys = (event) => {
        const isField = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target?.tagName);
        const key = event.key || '';
        const code = event.code || '';
        const legacyCode = Number(event.keyCode || event.which || 0);
        const nextKeys = ['ArrowRight', 'Right', 'PageDown', ' ', 'Space', 'Spacebar', 'Enter', 'MediaTrackNext', 'BrowserForward'];
        const previousKeys = ['ArrowLeft', 'Left', 'PageUp', 'MediaTrackPrevious', 'BrowserBack'];
        const isNext = nextKeys.includes(key) || nextKeys.includes(code) || [13, 32, 34, 39, 167, 176].includes(legacyCode);
        const isPrevious = previousKeys.includes(key) || previousKeys.includes(code) || [33, 37, 166, 177].includes(legacyCode);
        if (isNext) {
            if (isField && ([' ', 'Space', 'Spacebar', 'Enter'].includes(key) || ['Space', 'Enter'].includes(code))) return;
            event.preventDefault();
            requestGo(1);
        } else if (isPrevious) {
            event.preventDefault();
            requestGo(-1);
        } else if (key.toLowerCase() === 'f') {
            document.documentElement.requestFullscreen?.();
        } else if (key.toLowerCase() === 'h') {
            document.body.classList.toggle('controls-hidden');
        }
    };

    previous.addEventListener('click', () => requestGo(-1));
    next.addEventListener('click', () => requestGo(1));
    fullscreen.addEventListener('click', () => document.documentElement.requestFullscreen?.());
    overviewButton.addEventListener('click', () => overview.showModal());
    overviewClose.addEventListener('click', () => overview.close());
    document.addEventListener('keydown', handleKeys);
    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin || event.source !== window.parent) return;
        if (event.data?.type === 'cmc-presentation-go') {
            requestGo(Number(event.data.direction) < 0 ? -1 : 1);
        }
    });
    sceneList.innerHTML = scenes.map((scene) => `<li><strong>${scene.title}</strong><span>${scene.cue}</span></li>`).join('');
    renderScene(current);
})();
