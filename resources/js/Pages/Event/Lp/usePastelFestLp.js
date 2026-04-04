import { onMounted, onUnmounted } from 'vue';

/**
 * lp_design/index.html インラインスクリプト相当（ルート要素配下にスコープ）
 */
export function usePastelFestLp(rootRef) {
    const cleanups = [];

    onMounted(() => {
        const root = rootRef.value;
        if (!root || typeof document === 'undefined') {
            return;
        }

        const onAnchorClick = (e) => {
            const a = e.target.closest?.('a[href^="#"]');
            if (!a || !root.contains(a)) return;
            const href = a.getAttribute('href');
            if (!href || href === '#') return;
            const t = root.querySelector(href);
            if (t) {
                e.preventDefault();
                t.scrollIntoView({ behavior: 'smooth' });
            }
        };
        root.addEventListener('click', onAnchorClick);
        cleanups.push(() => root.removeEventListener('click', onAnchorClick));

        const track = root.querySelector('#galleryTrack');
        let galleryRaf = 0;
        if (track) {
            track.innerHTML += track.innerHTML;
            let pos = 0;
            let paused = false;
            const speed = 0.5;
            const onEnter = () => { paused = true; };
            const onLeave = () => { paused = false; };
            track.addEventListener('mouseenter', onEnter);
            track.addEventListener('mouseleave', onLeave);
            const tick = () => {
                if (!paused) {
                    pos += speed;
                    if (pos >= track.scrollWidth / 2) pos = 0;
                    track.style.transform = `translateX(-${pos}px)`;
                }
                galleryRaf = requestAnimationFrame(tick);
            };
            galleryRaf = requestAnimationFrame(tick);
            cleanups.push(() => {
                cancelAnimationFrame(galleryRaf);
                track.removeEventListener('mouseenter', onEnter);
                track.removeEventListener('mouseleave', onLeave);
            });
        }

        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const siblings = entry.target.parentElement
                        ? Array.from(entry.target.parentElement.querySelectorAll(':scope > .anim'))
                        : [];
                    const idx = siblings.indexOf(entry.target);
                    const delay = idx >= 0 ? idx * 120 : 0;
                    entry.target.style.transitionDelay = `${delay}ms`;
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        root.querySelectorAll('.anim').forEach((el) => io.observe(el));
        cleanups.push(() => io.disconnect());

        let scrollTicking = false;
        const onScroll = () => {
            if (scrollTicking) return;
            scrollTicking = true;
            requestAnimationFrame(() => {
                const s = window.scrollY;
                const heroImg = root.querySelector('.hero-photo img');
                if (heroImg && s < window.innerHeight * 1.5) {
                    heroImg.style.transform = `translateY(${s * 0.12}px)`;
                }
                const cta = root.querySelector('.fixed-cta');
                if (cta) {
                    cta.style.transform = s > 300 ? 'translateY(0)' : 'translateY(100%)';
                }
                scrollTicking = false;
            });
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        cleanups.push(() => window.removeEventListener('scroll', onScroll));

        function animateCount(el) {
            const target = parseInt(el.dataset.count, 10);
            if (!target) return;
            const duration = 1200;
            const start = performance.now();
            function step(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - (1 - progress) ** 3;
                const current = Math.floor(target * eased);
                el.textContent = current.toLocaleString();
                if (progress < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }
        const countObserver = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    animateCount(e.target);
                    countObserver.unobserve(e.target);
                }
            });
        }, { threshold: 0.5 });
        root.querySelectorAll('[data-count]').forEach((el) => countObserver.observe(el));
        cleanups.push(() => countObserver.disconnect());

        const lineupNum = root.querySelector('.lineup-num');
        let lineupObs = null;
        if (lineupNum) {
            lineupObs = new IntersectionObserver((entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) {
                        const target = 3000;
                        const duration = 1400;
                        const start = performance.now();
                        function step(now) {
                            const p = Math.min((now - start) / duration, 1);
                            const eased = 1 - (1 - p) ** 3;
                            e.target.textContent = Math.floor(target * eased).toLocaleString();
                            if (p < 1) requestAnimationFrame(step);
                        }
                        requestAnimationFrame(step);
                        lineupObs.unobserve(e.target);
                    }
                });
            }, { threshold: 0.5 });
            lineupObs.observe(lineupNum);
        }
        if (lineupObs) {
            cleanups.push(() => lineupObs.disconnect());
        }

        const hero = root.querySelector('.hero');
        let sakuraContainer = null;
        if (hero) {
            sakuraContainer = document.createElement('div');
            sakuraContainer.className = 'sakura-container';
            sakuraContainer.setAttribute('aria-hidden', 'true');
            hero.appendChild(sakuraContainer);
            for (let i = 0; i < 15; i += 1) {
                const petal = document.createElement('div');
                petal.className = 'sakura-petal';
                petal.style.cssText = `
            left: ${Math.random() * 100}%;
            animation-delay: ${Math.random() * 6}s;
            animation-duration: ${5 + Math.random() * 5}s;
            width: ${8 + Math.random() * 8}px;
            height: ${8 + Math.random() * 8}px;
            opacity: ${0.3 + Math.random() * 0.4};
          `;
                sakuraContainer.appendChild(petal);
            }
            cleanups.push(() => {
                if (sakuraContainer?.parentNode) {
                    sakuraContainer.parentNode.removeChild(sakuraContainer);
                }
            });
        }

        const items = root.querySelectorAll('.hero-event-label, .hero-title-deco, .hero-title-accent, .hero-location, .hero-dates, .hero-catch, .hero-cta');
        items.forEach((el, i) => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(24px)';
            el.style.transition = 'opacity .7s ease, transform .7s ease';
            setTimeout(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, 300 + i * 150);
        });

        const planCards = root.querySelectorAll('.plan-card');
        const planHandlers = [];
        planCards.forEach((card) => {
            const mm = (e) => {
                const rect = card.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width - 0.5;
                const y = (e.clientY - rect.top) / rect.height - 0.5;
                card.style.transform = `perspective(600px) rotateY(${x * 4}deg) rotateX(${-y * 4}deg)`;
            };
            const ml = () => {
                card.style.transform = 'perspective(600px) rotateY(0) rotateX(0)';
                card.style.transition = 'transform .4s ease';
            };
            const me = () => {
                card.style.transition = 'transform .1s ease';
            };
            card.addEventListener('mousemove', mm);
            card.addEventListener('mouseleave', ml);
            card.addEventListener('mouseenter', me);
            planHandlers.push(() => {
                card.removeEventListener('mousemove', mm);
                card.removeEventListener('mouseleave', ml);
                card.removeEventListener('mouseenter', me);
            });
        });
        cleanups.push(() => planHandlers.forEach((fn) => fn()));

        const cta = root.querySelector('.fixed-cta');
        if (cta) {
            cta.style.transition = 'transform .4s cubic-bezier(.22,1,.36,1)';
            cta.style.transform = 'translateY(100%)';
        }
        onScroll();
    });

    onUnmounted(() => {
        cleanups.forEach((fn) => fn());
    });
}
