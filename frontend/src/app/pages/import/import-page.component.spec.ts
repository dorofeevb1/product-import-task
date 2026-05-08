import { TestBed } from '@angular/core/testing';
import { throwError } from 'rxjs';
import { ImportPageComponent } from './import-page.component';
import { ImportService } from '../../services/import.service';

describe('ImportPageComponent error handling', () => {
  it('resets uploading and shows error on upload failure', () => {
    const importServiceStub = {
      upload: () => throwError(() => new Error('network failed')),
      status: () => throwError(() => new Error('unused')),
    };

    TestBed.configureTestingModule({
      imports: [ImportPageComponent],
      providers: [{ provide: ImportService, useValue: importServiceStub }],
    });

    const fixture = TestBed.createComponent(ImportPageComponent);
    const component = fixture.componentInstance;
    component.file = new File(['a'], 'products.xlsx');
    component.upload();

    expect(component.uploading).toBeFalse();
    expect(component.errorMessage).toBe('Непредвиденная ошибка. Попробуйте еще раз.');
  });
});
