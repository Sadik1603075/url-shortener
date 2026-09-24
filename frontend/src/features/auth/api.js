import apiClient from '../../lib/apiClient';

export async function login(credentials) {
    const response = await apiClient.post('/auth/login', credentials);

    return response.data;
}

export async function logout() {
  await apiClient.post('/auth/logout');
}