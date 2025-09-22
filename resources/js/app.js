 import './bootstrap';

document.addEventListener('DOMContentLoaded', function() {
    // Checkbox functionality
    const checkAlls = document.querySelectorAll('.checkall');
    const checkboxes = document.querySelectorAll('.row-checkbox');

    checkAlls.forEach(checkAll => {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    });

    // Update check all state based on individual checkboxes
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = Array.from(checkboxes).every(c => c.checked);
            const someChecked = Array.from(checkboxes).some(c => c.checked);
            checkAlls.forEach(checkAll => {
                checkAll.checked = allChecked;
                checkAll.indeterminate = someChecked && !allChecked;
            });
        });
    });
});
