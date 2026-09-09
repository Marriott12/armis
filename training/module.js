document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-training-table]').forEach((table) => {
    table.querySelectorAll('th[data-sort]').forEach((header, index) => {
      const sort = () => {
        const ascending = header.dataset.order !== 'asc';
        const rows = [...table.tBodies[0].rows].filter((row) => row.cells.length > index);
        rows.sort((left, right) => left.cells[index].textContent.trim().localeCompare(right.cells[index].textContent.trim(), undefined, { numeric: true }));
        if (!ascending) rows.reverse();
        rows.forEach((row) => table.tBodies[0].appendChild(row));
        table.querySelectorAll('th[data-sort]').forEach((item) => delete item.dataset.order);
        header.dataset.order = ascending ? 'asc' : 'desc';
      };
      header.tabIndex = 0;
      header.setAttribute('role', 'button');
      header.addEventListener('click', sort);
      header.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); sort(); } });
    });
  });
});