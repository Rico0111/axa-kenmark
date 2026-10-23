/* Shared mobile navigation. No requests, credentials, storage or application mutations. */
(() => {
  'use strict';
  const mobile = window.matchMedia('(max-width: 900px)');
  const scrollMotion = window.matchMedia('(max-width: 768px)');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const focusable = 'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  document.querySelectorAll('[data-navigation]').forEach(root => {
    const toggle = root.querySelector('[data-nav-toggle]');
    const panel = root.querySelector('[data-nav-panel]');
    const backdrop = root.querySelector('[data-nav-backdrop]');
    if (!toggle || !panel || !backdrop) return;
    const isAdmin = root.dataset.navKind === 'admin';
    const openLabel = isAdmin ? 'Open administration navigation' : 'Open navigation';
    let opened = false;
    let restoreTimer;
    let frame = 0;
    let lastY = window.scrollY;
    let editing = false;
    let pointerActive = false;
    const inertBefore = new Map();

    const showHeader = () => root.classList.remove('nav-is-scrolling');
    const editable = element => element instanceof HTMLElement &&
      (element.matches('input:not([type="hidden"]), textarea, select, [contenteditable]:not([contenteditable="false"]), [role="textbox"]') || element.isContentEditable);
    const pinned = () => opened || (root.contains(document.activeElement) && document.activeElement.matches(':focus-visible')) ||
      (isAdmin && (editing || pointerActive || editable(document.activeElement) ||
        document.activeElement?.closest('form, [role="dialog"], dialog[open]')));
    const visibleControls = () => [...root.querySelectorAll(focusable)].filter(el =>
      !el.closest('[inert]') && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');

    function lockBackground(lock) {
      if (lock) {
        // Inert only siblings along the ancestry, never the navigation itself.
        let branch = root;
        while (branch && branch !== document.body) {
          for (const sibling of branch.parentElement.children) {
            if (sibling !== branch && !sibling.matches('script, style, link')) {
              inertBefore.set(sibling, sibling.inert);
              sibling.inert = true;
            }
          }
          branch = branch.parentElement;
        }
        document.documentElement.classList.add('nav-scroll-locked');
        document.body.classList.add('nav-scroll-locked');
      } else {
        inertBefore.forEach((value, element) => { element.inert = value; });
        inertBefore.clear();
        document.documentElement.classList.remove('nav-scroll-locked');
        document.body.classList.remove('nav-scroll-locked');
      }
    }

    function setOpen(value, restoreFocus = true) {
      const wasOpened = opened;
      opened = value && mobile.matches;
      clearTimeout(restoreTimer);
      showHeader();
      root.classList.toggle('nav-is-open', opened);
      toggle.setAttribute('aria-expanded', String(opened));
      toggle.setAttribute('aria-label', opened ? 'Close navigation' : openLabel);
      backdrop.hidden = !opened;
      // Move focus before making a closing panel inert.
      if (!opened && wasOpened && restoreFocus && panel.contains(document.activeElement)) toggle.focus({ preventScroll: true });
      panel.inert = mobile.matches && !opened;
      lockBackground(opened);
      if (opened) visibleControls().find(element => panel.contains(element))?.focus({ preventScroll: true });
    }

    toggle.addEventListener('click', () => setOpen(!opened));
    backdrop.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', event => {
      if (!opened) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false);
        toggle.focus({ preventScroll: true });
      } else if (event.key === 'Tab') {
        const controls = visibleControls();
        const index = controls.indexOf(document.activeElement);
        if (event.shiftKey && index <= 0) {
          event.preventDefault();
          controls.at(-1)?.focus();
        } else if (!event.shiftKey && (index === controls.length - 1 || index === -1)) {
          event.preventDefault();
          controls[0]?.focus();
        }
      }
    });
    document.addEventListener('focusin', event => {
      showHeader();
      if (opened && !root.contains(event.target)) toggle.focus({ preventScroll: true });
    });
    document.addEventListener('input', event => {
      if (isAdmin && editable(event.target)) { editing = true; showHeader(); }
    });
    document.addEventListener('focusout', () => {
      // Do not leave an editing latch after focus has actually exited the form.
      queueMicrotask(() => { if (!editable(document.activeElement)) editing = false; });
    });
    document.addEventListener('pointerdown', event => {
      if (isAdmin && event.target.closest('form, button, [role="dialog"], [contenteditable]')) {
        pointerActive = true;
        showHeader();
      }
    }, { passive: true });
    const releasePointer = () => { pointerActive = false; };
    document.addEventListener('pointerup', releasePointer, { passive: true });
    document.addEventListener('pointercancel', releasePointer, { passive: true });

    panel.addEventListener('click', event => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const link = event.target.closest('a[href]');
      if (!link || !opened) return;
      const destination = new URL(link.href, location.href);
      const samePage = destination.origin === location.origin && destination.pathname === location.pathname;
      const target = samePage && destination.hash ? document.getElementById(decodeURIComponent(destination.hash.slice(1))) : null;
      setOpen(false);
      if (target) {
        if (target instanceof HTMLDetailsElement) target.open = true;
        if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });
      }
    });

    const sectionLinks = [...panel.querySelectorAll('[data-nav-section]')];
    function updateCurrentSection() {
      if (isAdmin) return;
      const local = sectionLinks.filter(link => new URL(link.href).pathname === location.pathname);
      let current = null;
      for (const link of local) {
        const target = document.getElementById(link.dataset.navSection);
        if (target && target.getBoundingClientRect().top <= 150) current = link;
      }
      if (!current && local.length) current = local[0];
      sectionLinks.forEach(link => {
        if (link === current) link.setAttribute('aria-current', 'location');
        else link.removeAttribute('aria-current');
      });
    }
    function onScroll() {
      const y = Math.max(0, window.scrollY);
      const moved = Math.abs(y - lastY) > 1;
      lastY = y;
      if (!frame) frame = requestAnimationFrame(() => { updateCurrentSection(); frame = 0; });
      if (!scrollMotion.matches || !moved) return;
      clearTimeout(restoreTimer);
      // Reduced motion keeps the header visible instead of jumping it offscreen.
      root.classList.toggle('nav-is-scrolling', y > 80 && !pinned() && !reduced.matches);
      restoreTimer = setTimeout(showHeader, 300);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('hashchange', updateCurrentSection);
    window.addEventListener('blur', showHeader);
    window.visualViewport?.addEventListener('resize', showHeader, { passive: true });
    function syncViewport() {
      const hadNavigationFocus = root.contains(document.activeElement);
      setOpen(false, mobile.matches);
      toggle.hidden = !mobile.matches;
      panel.inert = mobile.matches;
      if (!mobile.matches && hadNavigationFocus && !visibleControls().includes(document.activeElement)) {
        visibleControls()[0]?.focus({ preventScroll: true });
      }
      lastY = window.scrollY;
      updateCurrentSection();
    }
    mobile.addEventListener('change', syncViewport);
    reduced.addEventListener('change', showHeader);
    scrollMotion.addEventListener('change', showHeader);
    window.addEventListener('pagehide', () => {
      clearTimeout(restoreTimer);
      cancelAnimationFrame(frame);
      frame = 0;
      pointerActive = false;
      showHeader();
    });
    window.addEventListener('pageshow', syncViewport);
    root.classList.add('nav-ready');
    if (isAdmin) panel.querySelector('.admin-nav-link.is-active')?.setAttribute('aria-current', 'page');
    syncViewport();
  });
})();
