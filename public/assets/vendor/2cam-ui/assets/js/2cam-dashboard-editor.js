(function () {
  'use strict';
  const editor = document.querySelector('[data-cam-dashboard-editor]');
  if (!editor) return;
  let dragged = null;
  const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

  function refresh() {
    Array.from(editor.querySelectorAll('[data-cam-widget]')).forEach((card, index) => {
      const order = card.querySelector('[data-cam-order]');
      const cols = card.querySelector('[data-cam-cols]');
      const rows = card.querySelector('[data-cam-rows]');
      const active = card.querySelector('[data-cam-active]');
      if (order) order.value = String(index + 1);
      const c = clamp(Number(cols?.value || 4), 2, 12);
      const r = clamp(Number(rows?.value || 2), 1, 8);
      if (cols) cols.value = String(c);
      if (rows) rows.value = String(r);
      card.style.setProperty('--cam-widget-cols', String(c));
      card.style.setProperty('--cam-widget-rows', String(r));
      card.classList.toggle('is-disabled', active ? !active.checked : false);
      const label = card.querySelector('[data-cam-size-label]');
      if (label) label.textContent = c + ' columnas × ' + r + ' filas';
    });
  }

  function resize(card, deltaCols, deltaRows) {
    const cols = card.querySelector('[data-cam-cols]');
    const rows = card.querySelector('[data-cam-rows]');
    if (cols) cols.value = String(clamp(Number(cols.value || 4) + deltaCols, 2, 12));
    if (rows) rows.value = String(clamp(Number(rows.value || 2) + deltaRows, 1, 8));
    refresh();
  }

  editor.addEventListener('change', (event) => {
    if (event.target.matches('[data-cam-active]')) refresh();
  });

  editor.addEventListener('click', (event) => {
    const card = event.target.closest('[data-cam-widget]');
    if (!card) return;
    const dc = Number(event.target.dataset.camResizeCols || 0);
    const dr = Number(event.target.dataset.camResizeRows || 0);
    if (dc || dr) {
      event.preventDefault();
      resize(card, dc, dr);
    }
  });

  editor.addEventListener('dragstart', (event) => {
    const card = event.target.closest('[data-cam-widget]');
    if (!card || !event.target.closest('[data-cam-drag]')) {
      event.preventDefault();
      return;
    }
    dragged = card;
    card.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', card.dataset.camWidget || 'widget');
  });

  editor.addEventListener('dragend', () => {
    if (dragged) dragged.classList.remove('is-dragging');
    dragged = null;
    refresh();
  });

  editor.addEventListener('dragover', (event) => {
    if (!dragged) return;
    event.preventDefault();
    const target = event.target.closest('[data-cam-widget]');
    if (!target || target === dragged) return;
    const rect = target.getBoundingClientRect();
    const after = event.clientY > rect.top + rect.height / 2;
    editor.insertBefore(dragged, after ? target.nextSibling : target);
  });

  editor.querySelectorAll('[data-cam-resize-handle]').forEach((handle) => {
    handle.addEventListener('pointerdown', (event) => {
      event.preventDefault();
      const card = handle.closest('[data-cam-widget]');
      const cols = card.querySelector('[data-cam-cols]');
      const rows = card.querySelector('[data-cam-rows]');
      const startX = event.clientX;
      const startY = event.clientY;
      const startCols = Number(cols?.value || 4);
      const startRows = Number(rows?.value || 2);
      const colWidth = Math.max(40, editor.getBoundingClientRect().width / 12);
      const rowHeight = 72;
      handle.setPointerCapture(event.pointerId);
      card.classList.add('is-resizing');
      const move = (moveEvent) => {
        if (cols) cols.value = String(clamp(startCols + Math.round((moveEvent.clientX - startX) / colWidth), 2, 12));
        if (rows) rows.value = String(clamp(startRows + Math.round((moveEvent.clientY - startY) / rowHeight), 1, 8));
        refresh();
      };
      const up = (upEvent) => {
        handle.releasePointerCapture(upEvent.pointerId);
        card.classList.remove('is-resizing');
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', up);
        refresh();
      };
      handle.addEventListener('pointermove', move);
      handle.addEventListener('pointerup', up);
    });
  });

  refresh();
})();
