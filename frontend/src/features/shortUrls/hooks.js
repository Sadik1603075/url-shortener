import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';

import {
  createShortUrl,
  getShortUrls,
  updateShortUrl,
  deleteShortUrl,
} from './api';

export const shortUrlKeys = {
  all: ['shortUrls'],
  lists: () => [...shortUrlKeys.all, 'list'],
};

export function useShortUrls(params = {}) {
  return useQuery({
    queryKey: [...shortUrlKeys.lists(), params],
    queryFn: () => getShortUrls(params),
    keepPreviousData: true,
  });
}

export function useCreateShortUrl() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: createShortUrl,

    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: shortUrlKeys.lists(),
      });
    },
  });
}

export function useUpdateShortUrl() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, ...payload }) => updateShortUrl(id, payload),

    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: shortUrlKeys.all });
    },
  });
}

export function useDeleteShortUrl() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: deleteShortUrl,

    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: shortUrlKeys.all });
    },
  });
}
