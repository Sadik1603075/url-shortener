import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';

import {
  createShortUrl,
  getShortUrls,
} from './api';

export const shortUrlKeys = {
  all: ['shortUrls'],
  lists: () => [...shortUrlKeys.all, 'list'],
};

export function useShortUrls(params = {}) {
  return useQuery({
    queryKey: [...shortUrlKeys.lists(), params],
    queryFn: () => getShortUrls(params),
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