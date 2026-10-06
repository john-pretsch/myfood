// Phone photos are often larger than the server's upload limit, so shrink them first.
export function resizeImage(file, maxSize = 1600) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(img.src);
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not read image'))), 'image/jpeg', 0.85);
        };
        img.onerror = () => reject(new Error('Could not read image'));
        img.src = URL.createObjectURL(file);
    });
}
