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