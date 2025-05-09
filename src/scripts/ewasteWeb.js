// Back to top
const upButton = document.getElementById("upButton");

// Show button when scrolling down
window.onscroll = function () {
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        upButton.style.display = "block";
    } else {
        upButton.style.display = "none";
    }
};

// Scroll to top when button is clicked
upButton.onclick = function () {
    window.scrollTo({ top: 0, behavior: "smooth" });
};


document.addEventListener("DOMContentLoaded", function () {
    const toggleFormLink = document.getElementById("toggleForm");
    const loginForm = document.getElementById("loginForm");
    const signupForm = document.getElementById("signupForm");
    const formTitle = document.getElementById("formTitle");
    const formToggleText = document.getElementById("formToggleText");

    function toggleForms() {
        if (signupForm.classList.contains("hidden")) {
            signupForm.classList.remove("hidden");
            loginForm.classList.add("hidden");
            formTitle.textContent = "Sign Up";
            formToggleText.innerHTML = 'Already have an account? <a href="#" id="toggleForm">Log in</a>';
        } else {
            loginForm.classList.remove("hidden");
            signupForm.classList.add("hidden");
            formTitle.textContent = "Log in";
            formToggleText.innerHTML = 'New to site? <a href="#" id="toggleForm">Sign up</a>';
        }
    }

    document.body.addEventListener("click", function (event) {
        if (event.target && event.target.id === "toggleForm") {
            event.preventDefault();
            toggleForms();
        }
    });
});


// JavaScript for Category Filter

document.addEventListener('DOMContentLoaded', function() {
    const categoryFilter = document.getElementById('category-filter');
    const products = document.querySelectorAll('.product-card');
    
    // Listen for category change
    categoryFilter.addEventListener('change', function() {
        const selectedCategory = categoryFilter.value; 
        
        products.forEach(function(product) {
            const productCategory = product.getAttribute('value');  
            
            if (selectedCategory === 'all' || selectedCategory === productCategory) {
                product.style.display = 'block'; 
            } else {
                product.style.display = 'none';  
            }
        });
    });
});


//Javasccript for the Login, sign up Background
const loginBackground = document.querySelector('.login-background');
const signupBackground = document.querySelector('.signup-background');  


//message

document.getElementById("guestContactForm").addEventListener("submit", function (e) {
  e.preventDefault();

  // Simulate email sending (no real backend here)
  // You would need to connect to backend or email API like EmailJS for real emails
  setTimeout(function () {
    document.getElementById("popup").style.display = "flex";
  }, 500); // Simulate delay
});

document.getElementById("closePopup").addEventListener("click", function () {
  document.getElementById("popup").style.display = "none";
});
