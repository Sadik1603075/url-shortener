import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  fetchAccessCodes,
  createAccessCode,
  updateAccessCode,
  deleteAccessCode,
  sendAccessCodeEmail,
} from './api';

const KEYS = {
  list: (page) => ['accessCodes', 'list', page],
  all: () => ['accessCodes'],
};

export function useAccessCodes(page = 1) {
  return useQuery({
    queryKey: KEYS.list(page),
    queryFn: () => fetchAccessCodes(page),
  });
}

export function useCreateAccessCode() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: createAccessCode,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: KEYS.all() });
    },
  });
}

export function useUpdateAccessCode() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, ...data }) => updateAccessCode(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: KEYS.all() });
    },
  });
}

export function useDeleteAccessCode() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: deleteAccessCode,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: KEYS.all() });
    },
  });
}

export function useSendAccessCodeEmail() {
  return useMutation({
    mutationFn: sendAccessCodeEmail,
  });
}
