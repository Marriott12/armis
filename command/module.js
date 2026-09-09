document.addEventListener('DOMContentLoaded', () => {
  const links = [...document.querySelectorAll('#moduleMenu a.nav-link')];
  const palette = document.createElement('div');
  palette.className = 'modal fade';
  palette.id = 'commandPalette';
  palette.tabIndex = -1;
  palette.innerHTML = '<div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="h5 modal-title">Command navigation</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input class="form-control mb-3" type="search" placeholder="Search personnel pages and reports" aria-label="Search Command navigation"><div class="list-group"></div></div></div></div>';
  document.body.appendChild(palette);
  const paletteModal = window.bootstrap ? new bootstrap.Modal(palette) : null;
  const paletteInput = palette.querySelector('input');
  const paletteList = palette.querySelector('.list-group');
  const renderPalette = () => {
    const query = paletteInput.value.toLowerCase();
    const items = links.filter((link) => link.textContent.toLowerCase().includes(query)).map((link) => {
      const item = document.createElement('a');
      item.className = 'list-group-item list-group-item-action';
      item.href = link.href;
      item.textContent = link.textContent.trim();
      return item;
    });
    if (query) {
      const personnel = document.createElement('a');
      personnel.className = 'list-group-item list-group-item-action';
      personnel.href = `profiles.php?search=${encodeURIComponent(paletteInput.value.trim())}`;
      personnel.textContent = `Search personnel for "${paletteInput.value.trim()}"`;
      items.unshift(personnel);
    }
    paletteList.replaceChildren(...items);
  };
  paletteInput.addEventListener('input', renderPalette);
  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      renderPalette();
      paletteModal?.show();
      palette.addEventListener('shown.bs.modal', () => paletteInput.focus(), { once: true });
    }
  });

  document.querySelectorAll('[data-command-table]').forEach((table) => {
    const container = table.closest('.card') || table.parentElement;
    const controls = document.createElement('div');
    controls.className = 'command-table-controls d-flex justify-content-end mb-2';
    controls.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary" title="Toggle compact table rows"><i class="fas fa-compress-alt"></i><span class="visually-hidden">Toggle compact table rows</span></button>';
    controls.querySelector('button').addEventListener('click', () => table.classList.toggle('table-sm'));
    container.insertBefore(controls, table.parentElement === container ? table : table.parentElement);
    table.querySelectorAll('th').forEach((header, index) => {
      header.tabIndex = 0;
      header.setAttribute('role', 'button');
      header.setAttribute('aria-label', `Sort by ${header.textContent.trim()}`);
      const sort = () => {
        const rows = [...table.tBodies[0].rows];
        const ascending = header.dataset.sort !== 'asc';
        rows.sort((left, right) => left.cells[index].textContent.trim().localeCompare(right.cells[index].textContent.trim(), undefined, { numeric: true }));
        if (!ascending) rows.reverse();
        rows.forEach((row) => table.tBodies[0].appendChild(row));
        table.querySelectorAll('th').forEach((item) => delete item.dataset.sort);
        header.dataset.sort = ascending ? 'asc' : 'desc';
      };
      header.addEventListener('click', sort);
      header.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); sort(); } });
    });
  });

  document.querySelectorAll('[data-saved-filters]').forEach((form) => {
    const key = `armis-command-${form.dataset.savedFilters}`;
    const save = form.querySelector('[data-save-view]');
    const load = form.querySelector('[data-load-view]');
    save?.addEventListener('click', () => localStorage.setItem(key, new URLSearchParams(new FormData(form)).toString()));
    load?.addEventListener('click', () => {
      const values = new URLSearchParams(localStorage.getItem(key) || '');
      values.forEach((value, name) => { const input = form.elements.namedItem(name); if (input) input.value = value; });
      form.requestSubmit();
    });
  });

  document.querySelectorAll('[data-copy-value]').forEach((button) => button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(button.dataset.copyValue);
      button.innerHTML = '<i class="fas fa-check"></i> Copied';
    } catch (error) {
      button.textContent = 'Copy unavailable';
    }
  }));
});