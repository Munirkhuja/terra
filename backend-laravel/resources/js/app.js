import './bootstrap';

window.Laravel = window.Laravel || {};
axios.get('/auth/user').then(res => {
    window.Laravel.userId = res.data.id || {};
    Echo.private(`uploads.${window.Laravel.userId}`)
        .listen('ImageProcessed', (e) => {
            MoonShine.ui.toast(
                `Изображение ID ${e.upload.id} успешно обработано!`,
                'success'
            )
            window.location.reload();
        });
});
