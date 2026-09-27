import apiClient from '../../lib/apiClient';

export async function createShortUrl(payload) {
  const response = await apiClient.post('/urls', payload);

  return response.data;
}

export async function getShortUrls(params = {}) {
  const response = await apiClient.get('/admin/urls', {
    params,
  });

  return response.data;
}

export async function updateShortUrl(id, payload) {
  const response = await apiClient.patch(`/admin/urls/${id}`, payload);

  return response.data.data;
}

export async function deleteShortUrl(id) {
  await apiClient.delete(`/admin/urls/${id}`);
}
