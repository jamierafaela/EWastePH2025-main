document.addEventListener("DOMContentLoaded", function() {
    const formInputs = document.querySelectorAll('.right-side .form-control');
    const progressCircle = document.querySelector('.progress-ring__circle');
    const progressText = document.querySelector('.progress-text');

    const totalFields = formInputs.length;
    const circumference = 2 * Math.PI * 52; // radius = 52

    progressCircle.style.strokeDasharray = `${circumference}`;
    progressCircle.style.strokeDashoffset = `${circumference}`;

    function updateProgress() {
        let filledFields = 0;
        formInputs.forEach(input => {
            if (input.value.trim() !== '') {
                filledFields++;
            }
        });

        let progress = (filledFields / totalFields) * 100;
        let offset = circumference - (progress / 100) * circumference;

        progressCircle.style.strokeDashoffset = offset;
        progressText.textContent = `${Math.round(progress)}%`;
    }

    formInputs.forEach(input => {
        input.addEventListener('input', updateProgress);
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
