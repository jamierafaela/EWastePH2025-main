document.addEventListener("DOMContentLoaded", function() {
    const formInputs = document.querySelectorAll('.right-side .form-control'); // includes inputs and select
    const progressCircle = document.querySelector('.progress-ring__circle');
    const progressText = document.querySelector('.progress-text');

    const totalFields = formInputs.length;
    const circumference = 2 * Math.PI * 52; // radius = 52

    progressCircle.style.strokeDasharray = `${circumference}`;
    progressCircle.style.strokeDashoffset = `${circumference}`;

    function updateProgress() {
        let filledFields = 0;

        formInputs.forEach(input => {
            // Check for filled text input or selected dropdown value
            if (input.tagName === 'SELECT') {
                // Ignore the default option (value is usually empty)
                if (input.value.trim() !== '') {
                    filledFields++;
                }
            } else if (input.value.trim() !== '') {
                filledFields++;
            }
        });

        let progress = (filledFields / totalFields) * 100;
        let offset = circumference - (progress / 100) * circumference;

        progressCircle.style.strokeDashoffset = offset;
        progressText.textContent = `${Math.round(progress)}%`;
    }

    // Add event listener for all form inputs (text, select, etc.)
    formInputs.forEach(input => {
        input.addEventListener('input', updateProgress);
        input.addEventListener('change', updateProgress); // Needed for <select>
    });

    updateProgress(); // initialize on page load
});




//scroll
document.addEventListener('DOMContentLoaded', function() {
    const formGroups = document.querySelectorAll('.right-side .form-group');

    function checkVisibility() {
        formGroups.forEach(group => {
            const rect = group.getBoundingClientRect();
            if (rect.top < window.innerHeight - 50) { // If 50px from bottom
                group.classList.add('visible');
            }
        });
    }

    window.addEventListener('scroll', checkVisibility);
    window.addEventListener('load', checkVisibility); // Check when page first loads
});
