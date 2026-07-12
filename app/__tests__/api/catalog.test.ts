import {fetchCategories, fetchProducts} from '../../src/api/catalog';
import {setTokenGetter} from '../../src/api/client';

global.fetch = jest.fn();
beforeEach(() => {
  (fetch as jest.Mock).mockReset();
  setTokenGetter(() => 'tok');
});

it('fetchProducts appends category_id query', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {items: [], meta: {total: 0, page: 1, per_page: 20}},
    }),
  });

  await fetchProducts({categoryId: 2, page: 1});
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/\/products\?.*category_id=2/),
    expect.any(Object),
  );
});

it('fetchProducts omits category_id when null', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {items: [], meta: {total: 0, page: 1, per_page: 20}},
    }),
  });

  await fetchProducts({});
  expect(fetch).toHaveBeenCalledWith(
    expect.not.stringContaining('category_id'),
    expect.any(Object),
  );
});
