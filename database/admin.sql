-- Extensão enviada para o painel. Importar UMA VEZ após schema.sql/seed.sql.
-- Não reimporte schema.sql em uma loja com dados: ele apaga as tabelas.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL,
 slug VARCHAR(100) NOT NULL UNIQUE, active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_categories_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS coupons (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30) NOT NULL UNIQUE,
 type ENUM('percent','fixed') NOT NULL DEFAULT 'percent', value DECIMAL(10,2) NOT NULL,
 min_order DECIMAL(10,2) NOT NULL DEFAULT 0, max_uses INT UNSIGNED NULL,
 used_count INT UNSIGNED NOT NULL DEFAULT 0, starts_at DATETIME NULL, expires_at DATETIME NULL,
 active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_coupons_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS settings (
 setting_key VARCHAR(60) PRIMARY KEY, setting_value TEXT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS stock_movements (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, book_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL,
 type ENUM('entrada','saida','ajuste','venda','cancelamento') NOT NULL, quantity INT NOT NULL,
 stock_after INT UNSIGNED NOT NULL, note VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_stock_book(book_id), KEY idx_stock_created(created_at),
 CONSTRAINT fk_stock_book FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE CASCADE,
 CONSTRAINT fk_stock_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS order_status_history (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL,
 old_status VARCHAR(20) NULL, new_status VARCHAR(20) NOT NULL, note VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_osh_order(order_id),
 CONSTRAINT fk_osh_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_osh_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audit_logs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NULL, action VARCHAR(60) NOT NULL,
 entity VARCHAR(40) NOT NULL, entity_id INT UNSIGNED NULL, details TEXT NULL, ip VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_audit_user(user_id),
 KEY idx_audit_entity(entity,entity_id), KEY idx_audit_created(created_at),
 CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS login_attempts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, ip VARCHAR(45) NOT NULL,
 success TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_login_email(email,created_at), KEY idx_login_ip(ip,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE users ADD active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
 ADD last_login_at DATETIME NULL AFTER active, ADD KEY idx_users_active(active);
ALTER TABLE books ADD category_id INT UNSIGNED NULL AFTER author,
 ADD isbn VARCHAR(20) NULL AFTER category_id, ADD publisher VARCHAR(120) NULL AFTER isbn,
 ADD pages SMALLINT UNSIGNED NULL AFTER publisher, ADD cost_price DECIMAL(10,2) NULL AFTER price,
 ADD min_stock INT UNSIGNED NOT NULL DEFAULT 3 AFTER stock, ADD active TINYINT(1) NOT NULL DEFAULT 1 AFTER featured,
 ADD UNIQUE KEY uq_books_isbn(isbn), ADD KEY idx_books_category(category_id), ADD KEY idx_books_active(active),
 ADD CONSTRAINT fk_books_category FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL;
ALTER TABLE orders ADD discount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER shipping,
 ADD coupon_id INT UNSIGNED NULL AFTER discount, ADD coupon_code VARCHAR(30) NULL AFTER coupon_id,
 ADD tracking_code VARCHAR(40) NULL AFTER payment_reference, ADD admin_note TEXT NULL AFTER tracking_code,
 ADD paid_at DATETIME NULL, ADD shipped_at DATETIME NULL, ADD delivered_at DATETIME NULL, ADD cancelled_at DATETIME NULL,
 ADD KEY idx_orders_coupon(coupon_id),
 ADD CONSTRAINT fk_orders_coupon FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE SET NULL;
INSERT IGNORE INTO categories(name,slug) VALUES ('Romance','romance'),('Ficção','ficcao'),('Fantasia','fantasia'),('Infantil','infantil'),('Não ficção','nao-ficcao');
UPDATE books SET category_id=(SELECT id FROM categories WHERE slug='ficcao') WHERE category_id IS NULL;
INSERT IGNORE INTO settings(setting_key,setting_value) VALUES
 ('store_name','Livraria Aslan'),('store_email','contato@livraria.test'),('store_phone',''),('store_whatsapp',''),
 ('pix_key',''),('pix_holder',''),('shipping_fixed','15.00'),('free_shipping_min','150.00'),('maintenance_mode','0');
INSERT IGNORE INTO coupons(code,type,value,min_order,active) VALUES ('BEMVINDO10','percent',10,0,1);
CREATE OR REPLACE VIEW v_low_stock AS SELECT id,title,author,stock,min_stock FROM books WHERE active=1 AND stock<=min_stock;
CREATE OR REPLACE VIEW v_sales_by_day AS SELECT DATE(created_at) AS day,COUNT(*) AS orders_count,SUM(total) AS revenue FROM orders WHERE status IN ('pago','enviado','entregue') GROUP BY DATE(created_at);
CREATE OR REPLACE VIEW v_top_books AS SELECT oi.book_id,oi.title,SUM(oi.quantity) AS units_sold,SUM(oi.line_total) AS revenue FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status IN ('pago','enviado','entregue') GROUP BY oi.book_id,oi.title;
