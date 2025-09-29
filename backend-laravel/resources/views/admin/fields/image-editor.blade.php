@props([
    'value' => '',
    'files' => [],
    'isRemovable' => false,
    'canDownload' => false,
    'removableAttributes' => null,
    'hiddenAttributes' => null,
    'dropzoneAttributes' => null,
    'imageUrl'=> '',
    'preview'=>null,
])

<div x-data="imageEditor()" x-init="init()" class="image-editor">
    <!-- Moonshine file field. держим ref на обёртку, внутри найдём native input -->
    <x-moonshine::form.file
        :attributes="$attributes"
        :files="$files"
        :removable="$isRemovable"
        :removableAttributes="$removableAttributes"
        :hiddenAttributes="$hiddenAttributes"
        :dropzoneAttributes="$dropzoneAttributes"
        :imageable="true"
        x-ref="fileInputRef"
        @change="handleFile($event)"
    />
    <div class="flex space-x-2" style="margin-top: 0.5rem">
        <button type="button" @click="loadFromUrl($refs.urlInput.value)" class="btn">Загрузить</button>
        <input type="text" x-ref="urlInput" placeholder="Вставьте ссылку на фото" class="form-input w-full"/>
    </div>
    <!-- preview + controls -->
    <template x-if="previewUrl">
        <div class="mt-4" style="margin-top: 0.5rem">
            <div style="position: relative;max-width:480px; max-height:480px; overflow:hidden;">
                <img x-ref="previewImage" :src="previewUrl" alt="preview" style="max-width:100%; display:block;"/>
            </div>

            <div class="mt-2 space-x-2">
                <button type="button" @click="rotate(-90)" class="btn">⟲</button>
                <button type="button" @click="rotate(90)" class="btn">⟳</button>
                <button type="button" @click="reset()" class="btn">Reset</button>
                <button type="button" @click="cropAndReplaceFile()"
                        class="btn">Сохранить обрезанное
                </button>
                <button type="button" @click="cancel()" class="btn">Отмена</button>
            </div>
        </div>
    </template>
</div>

<script>
    function imageEditor() {
        return {
            // инициализируем превью из props (если есть)
            previewUrl: {!! json_encode($imageUrl ?? $preview ?? '') !!},
            cropper: null,
            originalFile: null,

            init() {
            },

            async ensureCropperLoaded() {
                if (window.Cropper) return;
                // подключаем CSS + JS UMD версии (будет доступен как window.Cropper)
                await new Promise((resolve) => {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/cropperjs@1.6.2/dist/cropper.css';
                    document.head.appendChild(link);

                    const s = document.createElement('script');
                    s.src = 'https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js';
                    s.onload = () => resolve();
                    s.onerror = () => {
                        console.error('Не удалось загрузить cropper.js');
                        resolve();
                    };
                    document.head.appendChild(s);
                });
            },

            // Возвращает native input[type=file] внутри x-ref (или сам ref если input)
            findNativeInput() {
                let name = '{{ $attributes['name'] }}';
                // ищем по x-ref, если он есть
                if (this.$refs.fileInputRef) {
                    const ref = this.$refs.fileInputRef;
                    if (ref.tagName && ref.tagName.toLowerCase() === 'input' && ref.type === 'file' && ref.name === name) {
                        return ref;
                    }
                    const native = ref.querySelector && ref.querySelector(`input[type="file"][name="${name}"]`);
                    if (native) return native;
                }

                // fallback: ищем по всему документу
                return document.querySelector(`input[type="file"][name="${name}"]`) || null;
            },
            async loadFromUrl(url) {
                if (!url) return;
                this.previewUrl = url;
                await this.initCropper();
            },

            async initCropper() {
                // подождём рендера, загрузим cropper и инициализируем
                await this.$nextTick();
                await this.ensureCropperLoaded();

                // дождёмся, пока img загрузится в DOM (в некоторых случаях нужно чуть подождать)
                const img = this.$refs.previewImage;
                if (!img) return;

                // Уничтожаем старый cropper если есть
                if (this.cropper) {
                    try {
                        this.cropper.destroy();
                    } catch (err) {
                    }
                    this.cropper = null;
                }

                // убедимся, что Cropper доступен
                const C = window.Cropper;
                if (!C) {
                    console.error('Cropper недоступен');
                    return;
                }

                this.cropper = new C(img, {
                    aspectRatio: NaN,
                    viewMode: 0,
                    autoCropArea: 0.9,
                    responsive: true,
                    background: true,
                    zoomable: true,
                    scalable: true,
                    movable: true,
                    cropBoxResizable: true,
                    minCropBoxWidth: 50,
                    minCropBoxHeight: 50,
                    dragMode: 'move',
                });
            },
            async handleFile(e) {
                const native = this.findNativeInput();
                const file = (e && e.target && e.target.files && e.target.files[0]) || (native && native.files && native.files[0]);
                if (!file) return;

                this.originalFile = file;
                // revoke старый url если был
                if (this._prevObjectUrl) URL.revokeObjectURL(this._prevObjectUrl);

                this.previewUrl = this._prevObjectUrl = URL.createObjectURL(file);
                await this.initCropper();
            },

            rotate(deg) {
                if (!this.cropper) return;
                this.cropper.rotate(deg);
            },

            reset() {
                if (!this.cropper) return;
                this.cropper.reset();
            },

            cancel() {
                // очистить превью и снять кроппер
                if (this.cropper) {
                    try {
                        this.cropper.destroy();
                    } catch (e) {
                    }
                    this.cropper = null;
                }
                if (this._prevObjectUrl) {
                    URL.revokeObjectURL(this._prevObjectUrl);
                    this._prevObjectUrl = null;
                }
                this.previewUrl = null;

                // очистить native input
                const native = this.findNativeInput();
                if (native) {
                    native.value = '';
                    native.dispatchEvent(new Event('change', {bubbles: true}));
                }
            },

            cropAndReplaceFile() {
                if (!this.cropper) return;
                // получаем обрезанный blob
                const croppedCanvas = this.cropper.getCroppedCanvas();
                if (!croppedCanvas) {
                    return;
                }

                croppedCanvas.toBlob((blob) => {
                    if (!blob) {
                        console.error('Не удалось получить blob из canvas');
                        return;
                    }
                    // создаём новый File (название можно сохранить из оригинала)
                    const filename = this.originalFile ? this.originalFile.name : 'cropped.png';
                    const newFile = new File([blob], filename, {type: blob.type || 'image/png'});

                    // заменяем files у native input
                    const dt = new DataTransfer();
                    dt.items.add(newFile);
                    const native = this.findNativeInput();
                    if (!native) {
                        console.error('Не найден native input для замены файлов');
                        return;
                    }
                    native.files = dt.files;
                    // триггерим событие change чтобы остальной код узнал об изменении
                    native.dispatchEvent(new Event('change', {bubbles: true}));

                    // обновляем превью на новый objectURL
                    if (this._prevObjectUrl) URL.revokeObjectURL(this._prevObjectUrl);
                    this.previewUrl = this._prevObjectUrl = URL.createObjectURL(newFile);

                    // уничтожаем cropper (кроме если хочешь дальше редактировать)
                    try {
                        this.cropper.destroy();
                    } catch (e) {
                    }
                    this.cropper = null;
                }, 'image/png');
            }
        }
    }
</script>
