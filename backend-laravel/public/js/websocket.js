document.addEventListener('DOMContentLoaded', function () {
    // Подключение Echo + Pusher
    if (typeof Echo === 'undefined') {
        console.warn('Echo не найден. Проверьте подключение Pusher/Echo.');
        return;
    }

    const userId = window.Laravel.userId;

    Echo.private(`uploads.${userId}`)
        .listen('ImageProcessed', function (e) {
            // Показываем toast
            if (typeof toastr !== 'undefined') {
                toastr.success(
                    `Изображение ID ${e.upload.id} успешно обработано!`,
                    'Обработка завершена'
                );
            } else {
                alert(`Изображение ID ${e.upload.id} успешно обработано!`);
            }
            window.location.reload();
        });
});
