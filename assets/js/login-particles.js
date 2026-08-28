(() => {
    'use strict';

    const particleCanvas = document.getElementById('cmcParticleCanvas');
    if (!particleCanvas) return;

    const particleHost = particleCanvas.closest('[data-cmc-particles]');
    const particleContext = particleCanvas.getContext('2d', { alpha: true, desynchronized: true });
    const maskCanvas = document.createElement('canvas');
    const maskContext = maskCanvas.getContext('2d', { willReadFrequently: true });
    if (!particleHost || !particleContext || !maskContext) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const particles = [];
    const cmcKeywords = [
        'CMCU', 'AI', 'AI Native', 'CMC Uni', 'Sáng tạo', 'Đổi mới', 'Tiên phong', 'Hiện đại',
        'Năng động', 'Khác biệt', 'Bản lĩnh', 'Tự tin', 'Đột phá', 'Thực chiến', 'Toàn cầu',
        'Hội nhập', 'Khai phóng', 'Chất lượng', 'Uy tín', 'Thân thiện', 'Cởi mở', 'Linh hoạt',
        'Chủ động', 'Dẫn đầu', 'Tử tế', 'Thông minh', 'Nhiệt huyết', 'Tận tâm', 'Gần gũi',
        'Sẵn sàng', 'Vững vàng', 'Bền bỉ', 'Đa năng', 'Cá tính', 'Trẻ trung', 'Chuyên nghiệp',
        'Thiết thực', 'Thực tiễn', 'Tương lai', 'Công nghệ', 'Kết nối', 'Bứt phá', 'Vươn xa',
        'Tỏa sáng', 'Khám phá', 'Dám nghĩ', 'Dám làm', 'Dám khác', 'Xứng tầm', 'Hữu ích',
        'Rộng mở', 'Sắc bén', 'Mạnh mẽ', 'Tinh gọn', 'Cầu tiến'
    ];
    const fillerKeywords = ['AI', 'AI', 'CMC', 'CMCU', 'AI', 'CMC', 'CMCU'];
    const baseRotationX = -.17;
    const baseRotationY = -.42;
    const baseRotationZ = -.075;
    let canvasWidth = 0;
    let canvasHeight = 0;
    let pixelRatio = 1;
    let rotationX = baseRotationX;
    let rotationY = baseRotationY;
    let rotationZ = baseRotationZ;
    let targetRotationX = baseRotationX;
    let targetRotationY = baseRotationY;
    let targetRotationZ = baseRotationZ;
    let rotationVelocityX = 0;
    let rotationVelocityY = 0;
    let rotationVelocityZ = 0;
    let sceneOffsetX = 0;
    let sceneOffsetY = 0;
    let targetOffsetX = 0;
    let targetOffsetY = 0;
    let offsetVelocityX = 0;
    let offsetVelocityY = 0;
    let animationFrame = 0;
    let particleReady = false;
    let particleVisible = true;
    let pointerActive = false;
    let pointerX = 0;
    let pointerY = 0;

    const seededRandom = (() => {
        let seed = 23082026;
        return () => {
            seed = (seed * 1664525 + 1013904223) >>> 0;
            return seed / 4294967296;
        };
    })();

    const drawParticles = () => {
        if (!particleReady || canvasWidth < 20 || canvasHeight < 20) return true;
        particleContext.clearRect(0, 0, canvasWidth, canvasHeight);

        rotationVelocityX = (rotationVelocityX + (targetRotationX - rotationX) * .045) * .79;
        rotationVelocityY = (rotationVelocityY + (targetRotationY - rotationY) * .045) * .79;
        rotationVelocityZ = (rotationVelocityZ + (targetRotationZ - rotationZ) * .04) * .8;
        offsetVelocityX = (offsetVelocityX + (targetOffsetX - sceneOffsetX) * .055) * .74;
        offsetVelocityY = (offsetVelocityY + (targetOffsetY - sceneOffsetY) * .055) * .74;
        rotationX += rotationVelocityX;
        rotationY += rotationVelocityY;
        rotationZ += rotationVelocityZ;
        sceneOffsetX += offsetVelocityX;
        sceneOffsetY += offsetVelocityY;

        const scale = Math.min(canvasWidth / 640, canvasHeight / 480);
        const centerX = canvasWidth * .54 + sceneOffsetX;
        const centerY = canvasHeight * .54 + sceneOffsetY;
        const cosX = Math.cos(rotationX);
        const sinX = Math.sin(rotationX);
        const cosY = Math.cos(rotationY);
        const sinY = Math.sin(rotationY);
        const cosZ = Math.cos(rotationZ);
        const sinZ = Math.sin(rotationZ);
        const focalLength = 620;
        const interactionRadius = Math.max(84, Math.min(132, Math.min(canvasWidth, canvasHeight) * .22));
        let hoverSettled = true;

        particleContext.textAlign = 'center';
        particleContext.textBaseline = 'middle';

        for (const particle of particles) {
            const sourceX = (particle.x - 270) * scale;
            const sourceY = (particle.y - 180) * scale;
            const sourceZ = particle.z * scale;
            const rotatedX = sourceX * cosY + sourceZ * sinY;
            const depthY = -sourceX * sinY + sourceZ * cosY;
            const rotatedY = sourceY * cosX - depthY * sinX;
            const depth = sourceY * sinX + depthY * cosX;
            const tiltedX = rotatedX * cosZ - rotatedY * sinZ;
            const tiltedY = rotatedX * sinZ + rotatedY * cosZ;
            const perspective = focalLength / Math.max(410, focalLength + depth);
            const baseScreenX = centerX + tiltedX * perspective;
            const baseScreenY = centerY + tiltedY * perspective;
            const depthRatio = Math.max(0, Math.min(1, (90 - depth) / 210));
            const minimumSize = particle.layer === 'feature' ? 7 : 4.4;
            const fontSize = Math.max(minimumSize, particle.size * scale * perspective);
            const fontWeight = particle.layer === 'feature' ? 850 : (particle.layer === 'fill' ? 800 : 750);
            let hoverTargetX = 0;
            let hoverTargetY = 0;

            if (pointerActive) {
                const deltaX = baseScreenX - pointerX;
                const deltaY = baseScreenY - pointerY;
                const distance = Math.hypot(deltaX, deltaY);
                if (distance < interactionRadius) {
                    const directionX = distance > .01 ? deltaX / distance : Math.cos(particle.tone * Math.PI * 2);
                    const directionY = distance > .01 ? deltaY / distance : Math.sin(particle.tone * Math.PI * 2);
                    const strength = (1 - distance / interactionRadius) ** 2;
                    const maximumPush = particle.layer === 'feature' ? 15 : (particle.layer === 'fill' ? 10 : 7);
                    hoverTargetX = directionX * strength * maximumPush;
                    hoverTargetY = directionY * strength * maximumPush;
                }
            }

            particle.hoverX = (particle.hoverX || 0) + (hoverTargetX - (particle.hoverX || 0)) * .18;
            particle.hoverY = (particle.hoverY || 0) + (hoverTargetY - (particle.hoverY || 0)) * .18;
            if (Math.abs(hoverTargetX - particle.hoverX) > .06 || Math.abs(hoverTargetY - particle.hoverY) > .06) {
                hoverSettled = false;
            }

            const screenX = baseScreenX + particle.hoverX;
            const screenY = baseScreenY + particle.hoverY;

            particleContext.font = `${fontWeight} ${fontSize}px "Segoe UI Variable", "Segoe UI", sans-serif`;
            if (particle.layer === 'feature') {
                particleContext.globalAlpha = (.96 + depthRatio * .04) * (.98 + particle.opacity * .02);
                particleContext.fillStyle = particle.tone > .76 ? '#ffffff' : '#e8fbff';
            } else if (particle.layer === 'fill') {
                particleContext.globalAlpha = (.78 + depthRatio * .18) * (.96 + particle.opacity * .04);
                particleContext.fillStyle = particle.tone > .68 ? '#ffffff' : '#c9f6fb';
            } else {
                particleContext.globalAlpha = (.38 + depthRatio * .18) * (.9 + particle.opacity * .1);
                particleContext.fillStyle = depthRatio > .58 ? '#a7eef5' : '#0874ad';
            }
            particleContext.fillText(particle.label, screenX, screenY);
        }

        particleContext.globalAlpha = 1;
        return hoverSettled;
    };

    const resizeParticles = () => {
        const bounds = particleHost.getBoundingClientRect();
        canvasWidth = Math.max(1, Math.round(bounds.width));
        canvasHeight = Math.max(1, Math.round(bounds.height));
        pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        particleCanvas.width = Math.round(canvasWidth * pixelRatio);
        particleCanvas.height = Math.round(canvasHeight * pixelRatio);
        particleContext.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        if (particleReady) drawParticles();
    };

    const animateParticles = () => {
        animationFrame = 0;
        if (!particleVisible || document.hidden || reduceMotion) return;
        const hoverSettled = drawParticles();
        const settled = Math.abs(targetRotationX - rotationX) < .0008
            && Math.abs(targetRotationY - rotationY) < .0008
            && Math.abs(targetRotationZ - rotationZ) < .0008
            && Math.abs(rotationVelocityX) < .0005
            && Math.abs(rotationVelocityY) < .0005
            && Math.abs(rotationVelocityZ) < .0005
            && Math.abs(targetOffsetX - sceneOffsetX) < .08
            && Math.abs(targetOffsetY - sceneOffsetY) < .08
            && hoverSettled;
        if (!settled) animationFrame = window.requestAnimationFrame(animateParticles);
    };

    const startParticleAnimation = () => {
        if (!animationFrame && particleReady && particleVisible && !document.hidden && !reduceMotion) {
            animationFrame = window.requestAnimationFrame(animateParticles);
        }
    };

    const particleImage = new Image();
    particleImage.decoding = 'async';
    particleImage.addEventListener('load', () => {
        maskCanvas.width = 540;
        maskCanvas.height = 360;
        maskContext.clearRect(0, 0, 540, 360);
        maskContext.drawImage(particleImage, 0, 0, 540, 360);
        const pixels = maskContext.getImageData(0, 0, 540, 360).data;
        const maskAlphaAt = (x, y) => {
            const sampleX = Math.max(0, Math.min(539, Math.round(x)));
            const sampleY = Math.max(0, Math.min(359, Math.round(y)));
            return pixels[(sampleY * 540 + sampleX) * 4 + 3];
        };

        for (let y = 4; y < 360; y += 8) {
            for (let x = 4; x < 540; x += 8) {
                const pixelIndex = (y * 540 + x) * 4;
                if (pixels[pixelIndex + 3] < 80 || seededRandom() < .08) continue;
                const fillerLabel = fillerKeywords[Math.floor(seededRandom() * fillerKeywords.length)];
                particles.push({
                    x: x + (seededRandom() - .5) * 1.8,
                    y: y + (seededRandom() - .5) * 1.8,
                    z: -43 + seededRandom() * 6,
                    size: 4.25 + seededRandom() * 1.35,
                    opacity: .82 + seededRandom() * .18,
                    tone: seededRandom(),
                    label: fillerLabel,
                    layer: 'fill'
                });
                if (seededRandom() < .46) {
                    const depthLayers = [10 + seededRandom() * 22, 58 + seededRandom() * 38];
                    depthLayers.forEach((depthZ, depthIndex) => {
                        particles.push({
                            x: x + (seededRandom() - .5) * 2.6,
                            y: y + (seededRandom() - .5) * 2.6,
                            z: depthZ,
                            size: 3.75 + seededRandom() * 1.15,
                            opacity: depthIndex === 0 ? .62 + seededRandom() * .16 : .44 + seededRandom() * .16,
                            tone: seededRandom(),
                            label: fillerLabel,
                            layer: 'depth'
                        });
                    });
                }
            }
        }

        const featureCandidates = [];
        for (let y = 8; y < 352; y += 8) {
            for (let x = 8; x < 532; x += 8) {
                if (maskAlphaAt(x, y) >= 80) featureCandidates.push({ x, y });
            }
        }
        for (let index = featureCandidates.length - 1; index > 0; index -= 1) {
            const swapIndex = Math.floor(seededRandom() * (index + 1));
            [featureCandidates[index], featureCandidates[swapIndex]] = [featureCandidates[swapIndex], featureCandidates[index]];
        }

        const featureLabels = [...cmcKeywords];
        for (let index = featureLabels.length - 1; index > 0; index -= 1) {
            const swapIndex = Math.floor(seededRandom() * (index + 1));
            [featureLabels[index], featureLabels[swapIndex]] = [featureLabels[swapIndex], featureLabels[index]];
        }

        const featureBoxes = [];
        let featureIndex = 0;
        for (const candidate of featureCandidates) {
            if (featureBoxes.length >= 58) break;
            const label = featureLabels[featureIndex % featureLabels.length];
            const labelLength = Array.from(label).length;
            const size = labelLength <= 3 ? 11 + seededRandom() * 2.8 : (labelLength <= 7 ? 8.6 + seededRandom() * 2.2 : 7 + seededRandom() * 1.7);
            const boxWidth = labelLength * size * .56 + 7;
            const boxHeight = size * 1.32 + 5;
            const box = {
                left: candidate.x - boxWidth / 2,
                right: candidate.x + boxWidth / 2,
                top: candidate.y - boxHeight / 2,
                bottom: candidate.y + boxHeight / 2
            };
            const overlaps = featureBoxes.some((placed) => !(box.right < placed.left || box.left > placed.right || box.bottom < placed.top || box.top > placed.bottom));
            if (overlaps) continue;
            featureBoxes.push(box);
            particles.push({
                x: candidate.x + (seededRandom() - .5) * 2,
                y: candidate.y + (seededRandom() - .5) * 2,
                z: -54 + seededRandom() * 5,
                size,
                opacity: .9 + seededRandom() * .1,
                tone: seededRandom(),
                label,
                layer: 'feature'
            });
            featureIndex += 1;
        }

        particles.sort((a, b) => b.z - a.z);
        particleReady = particles.length > 0;
        particleHost.classList.toggle('is-particle-ready', particleReady);
        resizeParticles();
        drawParticles();
    });
    particleImage.src = 'assets/img/cmc-university.svg';

    if (!reduceMotion) {
        particleHost.addEventListener('pointermove', (event) => {
            const bounds = particleHost.getBoundingClientRect();
            const normalizedX = (event.clientX - bounds.left) / bounds.width;
            const normalizedY = (event.clientY - bounds.top) / bounds.height;
            pointerActive = true;
            pointerX = event.clientX - bounds.left;
            pointerY = event.clientY - bounds.top;
            targetRotationY = baseRotationY + (normalizedX - .5) * .8;
            targetRotationX = baseRotationX + (.5 - normalizedY) * .5;
            targetRotationZ = baseRotationZ + (normalizedX - .5) * .11;
            targetOffsetX = (normalizedX - .5) * 18;
            targetOffsetY = (normalizedY - .5) * 12;
            startParticleAnimation();
        }, { passive: true });
        particleHost.addEventListener('pointerleave', () => {
            pointerActive = false;
            targetRotationX = baseRotationX;
            targetRotationY = baseRotationY;
            targetRotationZ = baseRotationZ;
            targetOffsetX = 0;
            targetOffsetY = 0;
            startParticleAnimation();
        });
    }

    if ('ResizeObserver' in window) {
        new ResizeObserver(resizeParticles).observe(particleHost);
    } else {
        window.addEventListener('resize', resizeParticles, { passive: true });
    }

    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            particleVisible = entry.isIntersecting;
            if (particleVisible) drawParticles();
            else if (animationFrame) {
                window.cancelAnimationFrame(animationFrame);
                animationFrame = 0;
            }
        }, { threshold: .05 }).observe(particleHost);
    }

    document.addEventListener('visibilitychange', startParticleAnimation);
})();
