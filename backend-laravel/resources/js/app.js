import './bootstrap';

import Echo from 'laravel-echo';

window.Pusher = require('pusher-js');

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
});

const userId = window.Laravel.userId;

Echo.private(`uploads.${userId}`)
    .listen('ImageProcessed', (e) => {
        if (typeof toastr !== 'undefined') {
            MoonShine.ui.toast(
                `Изображение ID ${e.upload.id} успешно обработано!`,
                'success'
            )
        } else {
            alert(`Изображение ID ${e.upload.id} успешно обработано!`);
        }
        window.location.reload();
    });
