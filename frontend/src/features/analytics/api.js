import apiClient from '../../lib/apiClient';

export async function getOverview(days = 30) {
  const response = await apiClient.get('/admin/analytics/overview', {
    params: { days },
  });

  return response.data.data;
}
