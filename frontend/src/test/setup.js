// Vitest global setup: extends `expect` with jest-dom matchers and clears the
// DOM between tests so renders don't leak into one another.
import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

afterEach(() => {
  cleanup();
  // Isolate the auth store / anything using localStorage between tests.
  try {
    localStorage.clear();
  } catch {
    // ignore (storage unavailable)
  }
});
