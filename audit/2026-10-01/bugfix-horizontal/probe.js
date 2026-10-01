// Run in DevTools on the affected Elementor page after Horizontal Scroll initializes.
(() => {
  const roots = [...document.querySelectorAll('[data-marrison-horizontal-scroll]')];
  return roots.map(root => {
    const scene = root.closest('.marrison-horizontal-scroll-scene');
    const viewport = scene && scene.querySelector('.marrison-horizontal-scroll-viewport');
    const mover = scene && scene.querySelector('.marrison-horizontal-scroll-mover');
    const children = mover ? [...mover.children].map((el, i) => ({
      i, id: el.getAttribute('data-id') || el.className,
      rectWidth: el.getBoundingClientRect().width,
      rectHeight: el.getBoundingClientRect().height,
      offsetWidth: el.offsetWidth,
      flex: getComputedStyle(el).flex,
      flexBasis: getComputedStyle(el).flexBasis,
      width: getComputedStyle(el).width,
      minWidth: getComputedStyle(el).minWidth,
      display: getComputedStyle(el).display,
    })) : [];
    return {
      root: root.className,
      sceneHeight: scene && scene.style.height,
      viewportHeight: viewport && viewport.offsetHeight,
      moverScrollWidth: mover && mover.scrollWidth,
      moverRectWidth: mover && mover.getBoundingClientRect().width,
      verticalDistance: root.__marrisonHorizontalScrollInstance && root.__marrisonHorizontalScrollInstance.verticalDistance,
      children,
    };
  });
})();
