(function () {
  'use strict';
  const form = document.querySelector('[data-cam-appearance-form]');
  const preview = document.querySelector('[data-cam-appearance-preview]');
  if (!form || !preview) return;
  let palettes = {};
  try { palettes = JSON.parse(preview.dataset.camPalettes || '{}'); } catch (_) {}

  function update() {
    const value = (name, fallback) => form.elements[name]?.value || fallback;
    preview.style.setProperty('--preview-primary', value('primary', '#5a40d6'));
    preview.style.setProperty('--preview-sidebar', value('sidebar', '#15182b'));
    preview.style.setProperty('--preview-topbar', value('topbar', '#ffffff'));
    preview.style.setProperty('--preview-background', value('background', '#f4f6f9'));
    preview.style.borderRadius = value('radius', '12') + 'px';
    preview.style.fontSize = value('font_scale', '100') + '%';
    preview.classList.toggle('preview-dark', value('appearance', 'light') === 'dark');
    document.querySelectorAll('[data-cam-radius-label]').forEach((node) => node.textContent = value('radius', '12'));
    document.querySelectorAll('[data-cam-font-label]').forEach((node) => node.textContent = value('font_scale', '100'));
    const colors = palettes[value('chart_palette', 'casamia')] || palettes.casamia || [];
    preview.querySelectorAll('[data-cam-preview-bar]').forEach((bar, index) => {
      if (colors.length) bar.style.background = colors[index % colors.length];
    });
  }

  form.querySelectorAll('[data-cam-preset]').forEach((button) => {
    button.addEventListener('click', () => {
      ['primary','sidebar','topbar','background','chart_palette','preset'].forEach((name) => {
        const field = form.elements[name];
        const key = 'cam' + name.replace(/(^|_)([a-z])/g, (_, __, c) => c.toUpperCase());
        if (field && button.dataset[key] !== undefined) field.value = button.dataset[key];
      });
      form.querySelectorAll('[data-cam-preset]').forEach((item) => item.classList.remove('selected'));
      button.classList.add('selected');
      update();
    });
  });

  form.addEventListener('input', update);
  form.addEventListener('change', update);
  update();
})();
