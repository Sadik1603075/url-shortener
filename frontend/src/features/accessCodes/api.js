import apiClient from '../../lib/apiClient';

export async function fetchAccessCodes(page = 1, perPage = 15) {
  const response = await apiClient.get('/admin/access-codes', {
    params: { page, per_page: perPage },
  });

  return response.data;
}

export async function createAccessCode(data) {
  const response = await apiClient.post('/admin/access-codes', data);

  return response.data.data;
}

export async function updateAccessCode(id, data) {
  const response = await apiClient.patch(`/admin/access-codes/${id}`, data);

  return response.data.data;
}

export async function deleteAccessCode(id) {
  await apiClient.delete(`/admin/access-codes/${id}`);
}

export async function sendAccessCodeEmail(id) {
  const response = await apiClient.post(`/admin/access-codes/${id}/send`);

  return response.data;
}
