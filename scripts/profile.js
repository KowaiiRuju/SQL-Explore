/**
 * profile.js - Logic for Profile page
 */

document.addEventListener('DOMContentLoaded', function () {
    // Profile Picture Preview
    const avatarInput = document.getElementById('uploadAvatar');
    if (avatarInput) {
        avatarInput.addEventListener('change', function () {
            previewImage(this, 'previewAvatar');
        });
    }

    function previewImage(input, targetId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = document.getElementById(targetId);
                if (img) {
                    img.src = e.target.result;
                    img.classList.remove('d-none');
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
});
