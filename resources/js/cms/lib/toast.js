import toast from 'react-hot-toast';

export function toastSuccess(message) {
    return toast.success(message);
}

export function toastError(error, fallback = 'Something went wrong') {
    const response = error?.response?.data;
    let message = response?.message || error?.message || fallback;

    if (response?.errors && typeof response.errors === 'object') {
        const firstField = Object.values(response.errors)[0];
        if (Array.isArray(firstField) && firstField.length > 0) {
            message = firstField[0];
        }
    }

    return toast.error(message);
}

export { toast };
