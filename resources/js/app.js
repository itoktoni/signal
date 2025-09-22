import './bootstrap';

document.addEventListener('DOMContentLoaded', function() {
    // Checkbox functionality
    const checkAll = document.querySelector('table.data-table thead input[type="checkbox"]');
    if (checkAll) {
        const checkboxes = document.querySelectorAll('table.data-table tbody input[type="checkbox"]');
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Update check all state based on individual checkboxes
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const allChecked = Array.from(checkboxes).every(c => c.checked);
                const someChecked = Array.from(checkboxes).some(c => c.checked);
                checkAll.checked = allChecked;
                checkAll.indeterminate = someChecked && !allChecked;
            });
        });
    }
});
