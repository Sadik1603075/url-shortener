import { useQuery } from '@tanstack/react-query';
import { getOverview } from './api';

export function useOverview(days = 30) {
  return useQuery({
    queryKey: ['analytics', 'overview', days],
    queryFn: () => getOverview(days),
    staleTime: 30_000,
  });
}
