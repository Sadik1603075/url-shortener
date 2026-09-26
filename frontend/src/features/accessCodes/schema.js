import { z } from 'zod';

export const createAccessCodeSchema = z.object({
  email: z
    .string()
    .min(1, 'Email is required.')
    .email('Enter a valid email address.'),
  description: z
    .string()
    .max(500, 'Description must be under 500 characters.')
    .optional(),
  expires_at: z
    .string()
    .optional()
    .refine(
      (val) => !val || new Date(val) > new Date(),
      { message: 'Expiry must be in the future.' },
    ),
});

export const updateAccessCodeSchema = z.object({
  description: z
    .string()
    .max(500, 'Description must be under 500 characters.')
    .optional(),
  expires_at: z
    .string()
    .optional()
    .refine(
      (val) => !val || new Date(val) > new Date(),
      { message: 'Expiry must be in the future.' },
    ),
});
