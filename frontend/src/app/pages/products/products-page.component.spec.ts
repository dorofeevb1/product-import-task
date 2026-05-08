import { TestBed } from '@angular/core/testing';
import { Store } from '@ngrx/store';
import { of } from 'rxjs';
import { ProductsPageComponent } from './products-page.component';
import { loadProducts } from '../../store/products.actions';
import { selectAllProducts, selectProductsError, selectProductsLoading, selectTotalPages } from '../../store/products.selectors';

describe('ProductsPageComponent filter paging behavior', () => {
  it('resets page to 1 when applying filters', () => {
    const dispatch = jasmine.createSpy('dispatch');
    const storeStub = {
      select: (selector: unknown) => {
        if (selector === selectAllProducts) return of([]);
        if (selector === selectProductsLoading) return of(false);
        if (selector === selectProductsError) return of(null);
        if (selector === selectTotalPages) return of(10);
        return of(null);
      },
      dispatch,
    };

    TestBed.configureTestingModule({
      imports: [ProductsPageComponent],
      providers: [{ provide: Store, useValue: storeStub }],
    });

    const fixture = TestBed.createComponent(ProductsPageComponent);
    const component = fixture.componentInstance;
    dispatch.calls.reset();

    component.currentPage = 5;
    component.name = 'desk';
    component.applyFilters();

    expect(component.currentPage).toBe(1);
    expect(dispatch).toHaveBeenCalledWith(
      loadProducts({
        page: 1,
        limit: component.limit,
        name: 'desk',
        priceMin: undefined,
        priceMax: undefined,
      })
    );
  });
});
