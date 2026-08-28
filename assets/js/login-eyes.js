(() => {
    'use strict';

    if (!document.querySelector('[data-login-eyes]')) return;

    const state = {
        mouseX: window.innerWidth / 2,
        mouseY: window.innerHeight / 2,
        isTyping: false,
        showPassword: false,
        passwordLength: 0,
        isPurpleBlinking: false,
        isBlackBlinking: false,
        isLookingAtEachOther: false,
        isPurplePeeking: false
    };

    const refs = {
        email: document.getElementById('loginEmail'),
        password: document.getElementById('loginPassword'),
        togglePassword: document.querySelector('[data-password-toggle="loginPassword"]'),
        purpleCharacter: document.getElementById('purple-character'),
        blackCharacter: document.getElementById('black-character'),
        orangeCharacter: document.getElementById('orange-character'),
        yellowCharacter: document.getElementById('yellow-character'),
        purpleEyes: document.getElementById('purple-eyes'),
        blackEyes: document.getElementById('black-eyes'),
        orangeEyes: document.getElementById('orange-eyes'),
        yellowEyes: document.getElementById('yellow-eyes'),
        yellowMouth: document.getElementById('yellow-mouth'),
        purpleEye1: document.getElementById('purple-eye-1'),
        purpleEye2: document.getElementById('purple-eye-2'),
        blackEye1: document.getElementById('black-eye-1'),
        blackEye2: document.getElementById('black-eye-2'),
        purplePupil1: document.getElementById('purple-pupil-1'),
        purplePupil2: document.getElementById('purple-pupil-2'),
        blackPupil1: document.getElementById('black-pupil-1'),
        blackPupil2: document.getElementById('black-pupil-2'),
        orangePupil1: document.getElementById('orange-pupil-1'),
        orangePupil2: document.getElementById('orange-pupil-2'),
        yellowPupil1: document.getElementById('yellow-pupil-1'),
        yellowPupil2: document.getElementById('yellow-pupil-2')
    };

    if (Object.values(refs).some((element) => !element)) return;

    const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

    const calculatePupilPosition = (element, maxDistance, forceLookX, forceLookY) => {
        if (forceLookX !== undefined && forceLookY !== undefined) {
            return { x: forceLookX, y: forceLookY };
        }

        const rect = element.getBoundingClientRect();
        const deltaX = state.mouseX - (rect.left + rect.width / 2);
        const deltaY = state.mouseY - (rect.top + rect.height / 2);
        const distance = Math.min(Math.sqrt(deltaX ** 2 + deltaY ** 2), maxDistance);
        const angle = Math.atan2(deltaY, deltaX);
        return { x: Math.cos(angle) * distance, y: Math.sin(angle) * distance };
    };

    const calculatePosition = (element) => {
        const rect = element.getBoundingClientRect();
        const deltaX = state.mouseX - (rect.left + rect.width / 2);
        const deltaY = state.mouseY - (rect.top + rect.height / 3);
        return {
            faceX: clamp(deltaX / 20, -15, 15),
            faceY: clamp(deltaY / 30, -10, 10),
            bodySkew: clamp(-deltaX / 120, -6, 6)
        };
    };

    const setElementTranslate = (element, position) => {
        element.style.transform = `translate(${position.x}px, ${position.y}px)`;
    };

    const updateEyeBall = (eyeElement, pupilElement, options) => {
        const { size, maxDistance, isBlinking, forceLookX, forceLookY } = options;
        eyeElement.style.width = `${size}px`;
        eyeElement.style.height = isBlinking ? '2px' : `${size}px`;
        pupilElement.style.display = isBlinking ? 'none' : 'block';
        if (!isBlinking) {
            setElementTranslate(pupilElement, calculatePupilPosition(eyeElement, maxDistance, forceLookX, forceLookY));
        }
    };

    const updatePupil = (element, options) => {
        const { maxDistance, forceLookX, forceLookY } = options;
        setElementTranslate(element, calculatePupilPosition(element, maxDistance, forceLookX, forceLookY));
    };

    const renderCharacters = () => {
        const purplePos = calculatePosition(refs.purpleCharacter);
        const blackPos = calculatePosition(refs.blackCharacter);
        const yellowPos = calculatePosition(refs.yellowCharacter);
        const orangePos = calculatePosition(refs.orangeCharacter);
        const isHidingPassword = state.passwordLength > 0 && !state.showPassword;
        const isShowingPassword = state.passwordLength > 0 && state.showPassword;

        refs.purpleCharacter.style.height = (state.isTyping || isHidingPassword) ? '440px' : '400px';
        refs.purpleCharacter.style.transform = isShowingPassword
            ? 'skewX(0deg)'
            : (state.isTyping || isHidingPassword)
                ? `skewX(${(purplePos.bodySkew || 0) - 12}deg) translateX(40px)`
                : `skewX(${purplePos.bodySkew || 0}deg)`;
        refs.blackCharacter.style.transform = isShowingPassword
            ? 'skewX(0deg)'
            : state.isLookingAtEachOther
                ? `skewX(${(blackPos.bodySkew || 0) * 1.5 + 10}deg) translateX(20px)`
                : (state.isTyping || isHidingPassword)
                    ? `skewX(${(blackPos.bodySkew || 0) * 1.5}deg)`
                    : `skewX(${blackPos.bodySkew || 0}deg)`;
        refs.orangeCharacter.style.transform = isShowingPassword ? 'skewX(0deg)' : `skewX(${orangePos.bodySkew || 0}deg)`;
        refs.yellowCharacter.style.transform = isShowingPassword ? 'skewX(0deg)' : `skewX(${yellowPos.bodySkew || 0}deg)`;

        refs.purpleEyes.style.left = isShowingPassword ? '20px' : state.isLookingAtEachOther ? '55px' : `${45 + purplePos.faceX}px`;
        refs.purpleEyes.style.top = isShowingPassword ? '35px' : state.isLookingAtEachOther ? '65px' : `${40 + purplePos.faceY}px`;
        refs.blackEyes.style.left = isShowingPassword ? '10px' : state.isLookingAtEachOther ? '32px' : `${26 + blackPos.faceX}px`;
        refs.blackEyes.style.top = isShowingPassword ? '28px' : state.isLookingAtEachOther ? '12px' : `${32 + blackPos.faceY}px`;
        refs.orangeEyes.style.left = isShowingPassword ? '50px' : `${82 + orangePos.faceX}px`;
        refs.orangeEyes.style.top = isShowingPassword ? '85px' : `${90 + orangePos.faceY}px`;
        refs.yellowEyes.style.left = isShowingPassword ? '20px' : `${52 + yellowPos.faceX}px`;
        refs.yellowEyes.style.top = isShowingPassword ? '35px' : `${40 + yellowPos.faceY}px`;
        refs.yellowMouth.style.left = isShowingPassword ? '10px' : `${40 + yellowPos.faceX}px`;
        refs.yellowMouth.style.top = isShowingPassword ? '88px' : `${88 + yellowPos.faceY}px`;

        const purpleForceX = isShowingPassword ? (state.isPurplePeeking ? 4 : -4) : state.isLookingAtEachOther ? 3 : undefined;
        const purpleForceY = isShowingPassword ? (state.isPurplePeeking ? 5 : -4) : state.isLookingAtEachOther ? 4 : undefined;
        const blackForceX = isShowingPassword ? -4 : state.isLookingAtEachOther ? 0 : undefined;
        const blackForceY = isShowingPassword ? -4 : state.isLookingAtEachOther ? -4 : undefined;
        const frontForceX = isShowingPassword ? -5 : undefined;
        const frontForceY = isShowingPassword ? -4 : undefined;

        updateEyeBall(refs.purpleEye1, refs.purplePupil1, { size: 18, maxDistance: 5, isBlinking: state.isPurpleBlinking, forceLookX: purpleForceX, forceLookY: purpleForceY });
        updateEyeBall(refs.purpleEye2, refs.purplePupil2, { size: 18, maxDistance: 5, isBlinking: state.isPurpleBlinking, forceLookX: purpleForceX, forceLookY: purpleForceY });
        updateEyeBall(refs.blackEye1, refs.blackPupil1, { size: 16, maxDistance: 4, isBlinking: state.isBlackBlinking, forceLookX: blackForceX, forceLookY: blackForceY });
        updateEyeBall(refs.blackEye2, refs.blackPupil2, { size: 16, maxDistance: 4, isBlinking: state.isBlackBlinking, forceLookX: blackForceX, forceLookY: blackForceY });
        updatePupil(refs.orangePupil1, { maxDistance: 5, forceLookX: frontForceX, forceLookY: frontForceY });
        updatePupil(refs.orangePupil2, { maxDistance: 5, forceLookX: frontForceX, forceLookY: frontForceY });
        updatePupil(refs.yellowPupil1, { maxDistance: 5, forceLookX: frontForceX, forceLookY: frontForceY });
        updatePupil(refs.yellowPupil2, { maxDistance: 5, forceLookX: frontForceX, forceLookY: frontForceY });
    };

    const scheduleBlink = (targetKey) => {
        window.setTimeout(() => {
            state[targetKey] = true;
            renderCharacters();
            window.setTimeout(() => {
                state[targetKey] = false;
                renderCharacters();
                scheduleBlink(targetKey);
            }, 150);
        }, Math.random() * 4000 + 3000);
    };

    let lookTimer = 0;
    let peekTimer = 0;
    let peekResetTimer = 0;

    const triggerLookingAtEachOther = () => {
        state.isLookingAtEachOther = true;
        renderCharacters();
        window.clearTimeout(lookTimer);
        lookTimer = window.setTimeout(() => {
            state.isLookingAtEachOther = false;
            renderCharacters();
        }, 800);
    };

    const schedulePeek = () => {
        window.clearTimeout(peekTimer);
        window.clearTimeout(peekResetTimer);
        if (state.passwordLength > 0 && state.showPassword) {
            peekTimer = window.setTimeout(() => {
                state.isPurplePeeking = true;
                renderCharacters();
                peekResetTimer = window.setTimeout(() => {
                    state.isPurplePeeking = false;
                    renderCharacters();
                    schedulePeek();
                }, 800);
            }, Math.random() * 3000 + 2000);
        } else {
            state.isPurplePeeking = false;
            renderCharacters();
        }
    };

    window.addEventListener('mousemove', (event) => {
        state.mouseX = event.clientX;
        state.mouseY = event.clientY;
        renderCharacters();
    }, { passive: true });

    refs.email.addEventListener('focus', () => {
        state.isTyping = true;
        triggerLookingAtEachOther();
        renderCharacters();
    });

    refs.email.addEventListener('blur', () => {
        state.isTyping = false;
        renderCharacters();
    });

    refs.password.addEventListener('input', (event) => {
        state.passwordLength = event.target.value.length;
        schedulePeek();
        renderCharacters();
    });

    refs.togglePassword.addEventListener('click', () => {
        window.requestAnimationFrame(() => {
            state.showPassword = refs.password.type === 'text';
            schedulePeek();
            renderCharacters();
        });
    });

    state.showPassword = refs.password.type === 'text';
    state.passwordLength = refs.password.value.length;
    scheduleBlink('isPurpleBlinking');
    scheduleBlink('isBlackBlinking');
    renderCharacters();
})();
