import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

/** Kadr kwadratowy zdjęcia profilowego na stronie edycji. */
export function registerPlayerAvatarCrop(Alpine) {
	Alpine.data('playerAvatarCrop', (config) => ({
		initials: config.initials || '?',
		previewUrl: config.avatarUrl || '',
		removed: false,
		cropping: false,
		cropper: null,
		sourceUrl: '',

		get showImage() {
			return Boolean(this.previewUrl) && !this.removed;
		},

		openPicker() {
			this.$refs.picker?.click();
		},

		pick(event) {
			const file = event.target.files?.[0];
			event.target.value = '';
			if (!file) {
				return;
			}

			this.destroyCropper();
			this.revokeSource();
			this.sourceUrl = URL.createObjectURL(file);
			this.cropping = true;
		},

		startCropper() {
			if (this.cropper) {
				return;
			}
			const image = this.$refs.cropImage;
			if (!image) {
				return;
			}
			this.cropper = new Cropper(image, {
				aspectRatio: 1,
				viewMode: 1,
				autoCropArea: 1,
				background: false,
				responsive: true,
			});
		},

		zoom(step) {
			this.cropper?.zoom(step);
		},

		async confirmCrop() {
			if (!this.cropper) {
				return;
			}

			const canvas = this.cropper.getCroppedCanvas({
				width: 512,
				height: 512,
				imageSmoothingQuality: 'high',
			});
			const blob = await new Promise((resolve) => {
				canvas.toBlob(resolve, 'image/jpeg', 0.85);
			});

			this.destroyCropper();
			this.cropping = false;
			this.revokeSource();

			if (!blob) {
				return;
			}

			const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
			const transfer = new DataTransfer();
			transfer.items.add(file);
			this.$refs.avatarInput.files = transfer.files;

			if (this.previewUrl.startsWith('blob:')) {
				URL.revokeObjectURL(this.previewUrl);
			}
			this.previewUrl = URL.createObjectURL(blob);
			this.removed = false;
		},

		cancelCrop() {
			this.destroyCropper();
			this.cropping = false;
			this.revokeSource();
		},

		remove() {
			this.cancelCrop();
			this.removed = true;
			if (this.previewUrl.startsWith('blob:')) {
				URL.revokeObjectURL(this.previewUrl);
			}
			this.previewUrl = '';
			if (this.$refs.avatarInput) {
				this.$refs.avatarInput.value = '';
			}
		},

		destroyCropper() {
			if (this.cropper) {
				this.cropper.destroy();
				this.cropper = null;
			}
		},

		revokeSource() {
			if (this.sourceUrl) {
				URL.revokeObjectURL(this.sourceUrl);
				this.sourceUrl = '';
			}
		},
	}));
}
