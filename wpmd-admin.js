document.addEventListener('DOMContentLoaded', function() {
    
    function initializeForm(formContainer) {
        if (!formContainer) return;

        const form = formContainer.querySelector('form');
        const dateRadios = formContainer.querySelectorAll('input[name="wpmd_date_option"]');
        const deleteAllCheckbox = formContainer.querySelector('input[name="wpmd_delete_all"]');
        
        const singleDateInput = formContainer.querySelector('input[name="wpmd_date_single"]');
        const singleDateCondition = formContainer.querySelector('select[name="wpmd_date_condition"]');
        const rangeDateStart = formContainer.querySelector('input[name="wpmd_date_start"]');
        const rangeDateEnd = formContainer.querySelector('input[name="wpmd_date_end"]');

        function updateDateFields() {
            // Disable everything first
            if (singleDateInput) singleDateInput.disabled = true;
            if (singleDateCondition) singleDateCondition.disabled = true;
            if (rangeDateStart) rangeDateStart.disabled = true;
            if (rangeDateEnd) rangeDateEnd.disabled = true;

            const selectedOption = formContainer.querySelector('input[name="wpmd_date_option"]:checked').value;
            
            if (selectedOption === 'single') {
                if (singleDateInput) singleDateInput.disabled = false;
                if (singleDateCondition) singleDateCondition.disabled = false;
            } else if (selectedOption === 'range') {
                if (rangeDateStart) rangeDateStart.disabled = false;
                if (rangeDateEnd) rangeDateEnd.disabled = false;
            }
        }

        dateRadios.forEach(radio => {
            radio.addEventListener('change', updateDateFields);
        });

        if (deleteAllCheckbox) {
            deleteAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                dateRadios.forEach(radio => {
                    radio.disabled = isChecked;
                    if (!isChecked && radio.value === 'all') {
                       radio.checked = true;
                    }
                });
                updateDateFields();
            });
        }
        
        // --- NEW: Add confirmation on form submit ---
        if (form) {
            form.addEventListener('submit', function(event) {
                // Determine if this is a delete or export action
                const isDelete = formContainer.id.includes('delete');
                const itemType = formContainer.id.includes('posts') ? 'posts' : 'users';
                let message = `Are you sure you want to export these ${itemType}?`;

                if (isDelete) {
                    message = `Are you sure you want to permanently delete these ${itemType}?\n\nThis action cannot be undone.`;
                }

                if (!confirm(message)) {
                    event.preventDefault(); // Stop the form submission if user clicks "Cancel"
                }
            });
        }
        
        // Initial setup on page load
        updateDateFields();
    }

    // Initialize all form containers on the page
    document.querySelectorAll('#export-posts-container, #delete-posts-container, #export-users-container, #delete-users-container').forEach(initializeForm);

});