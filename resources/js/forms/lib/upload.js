import axios from 'axios';

/**
 * Uploads a single image (logo or inline image block) to the Forms asset
 * endpoint and resolves to `{ path, url }`. axios attaches Laravel's
 * X-XSRF-TOKEN from the cookie automatically for this same-origin POST.
 *
 * @param {File} file
 * @returns {Promise<{ path: string, url: string }>}
 */
export async function uploadFormImage(file) {
  const data = new FormData();
  data.append('image', file);

  const response = await axios.post('/forms/assets', data, {
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
  });

  return response.data;
}
