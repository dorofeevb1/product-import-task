INSERT INTO products (external_code, name, description, price, discount)
VALUES
  ('SKU-100', 'Test Product 1', 'Description 1', 100.00, 20.00),
  ('SKU-101', 'Test Product 2', 'Description 2', 250.00, 10.00);

INSERT INTO product_attributes (product_id, attr_key, attr_value)
VALUES
  (1, 'Доп. поле Цвет', 'Красный'),
  (1, 'Доп. поле Материал', 'Хлопок'),
  (2, 'Доп. поле Вес', '1.2кг');

INSERT INTO product_images (product_id, url, path)
VALUES
  (1, 'https://example.com/p1.jpg', 'storage/images/SKU-100/p1.jpg'),
  (2, 'https://example.com/p2.jpg', 'storage/images/SKU-101/p2.jpg');
