document.addEventListener('DOMContentLoaded', function () {
    // Animate stats counter
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach(stat => {
        const target = parseInt(stat.textContent);
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            stat.textContent = Math.round(current);
        }, 30);
    });

    // Update greeting based on time
    function updateGreeting() {
        const hour = new Date().getHours();
        const greetingEl = document.querySelector('.greeting');
        const textEl = document.querySelector('.lead.text-muted');

        let greeting = "Hello";
        let message = "Ready to explore?";

        if (hour < 12) {
            greeting = "Good Morning";
            message = "Ready to explore the database?";
        } else if (hour < 18) {
            greeting = "Good Afternoon";
            message = "What would you like to do today?";
        } else {
            greeting = "Good Evening";
            message = "Time to dive into some data.";
        }

        // Update with animation
        if (greetingEl && textEl) {
            greetingEl.style.opacity = '0';
            textEl.style.opacity = '0';

            setTimeout(() => {
                // Note: user name is server-side, so we just update the greeting part or expect it to be handled if we wanted full dynamic
                // For simplicity in JS file, we might just update the text content if we had the name stored in a data attribute
                // But preserving original logic:
                // We will rely on the server-rendered initial state, but dynamic updates might need the name.
                // Let's assume the element contains the full text and we replace the greeting part.

                // Since this is client side now, we can't easily access the PHP session name without passing it.
                // However, the original code had inline PHP. 
                // Strategy: The original code re-rendered the whole string. 
                // We will keep it simple: just update the greeting text structure if possible, OR
                // leave the greeting update logic to just time-based if we can't get the name clearly.
                // Actually, let's grab the name from the current text if we can.
                const currentText = greetingEl.textContent;
                // Extract name: "Hello, Name!" -> split by comma
                const namePart = currentText.substring(currentText.indexOf(',') + 1).trim();

                greetingEl.textContent = greeting + ", " + namePart;
                textEl.textContent = message;
                greetingEl.style.opacity = '1';
                textEl.style.opacity = '1';
            }, 300);
        }
    }

    // Update every 5 minutes
    setInterval(updateGreeting, 300000);
});
