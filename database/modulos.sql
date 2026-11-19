-- SQL dos módulos enviado pelo responsável pela loja.
-- Importar UMA VEZ depois de schema.sql, seed.sql, admin.sql e perfil_usuario.sql.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS payments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL,
 method ENUM('pix','cartao_credito','cartao_debito','boleto','outro') NOT NULL DEFAULT 'pix',
 gateway VARCHAR(30) NOT NULL DEFAULT 'manual', gateway_id VARCHAR(100) NULL,
 amount DECIMAL(10,2) NOT NULL, installments TINYINT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('pendente','aprovado','recusado','estornado','expirado','cancelado') NOT NULL DEFAULT 'pendente',
 pix_code TEXT NULL, pix_expires_at DATETIME NULL, proof_image VARCHAR(255) NULL,
 refund_amount DECIMAL(10,2) NULL, refund_reason VARCHAR(255) NULL, gateway_response TEXT NULL,
 paid_at DATETIME NULL, refunded_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_payments_gateway(gateway,gateway_id), KEY idx_payments_order(order_id),
 KEY idx_payments_status(status), KEY idx_payments_created(created_at),
 CONSTRAINT fk_payments_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS shipping_rates (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, state CHAR(2) NULL,
 cep_start CHAR(8) NULL, cep_end CHAR(8) NULL, price DECIMAL(10,2) NOT NULL, free_above DECIMAL(10,2) NULL,
 days_min TINYINT UNSIGNED NOT NULL DEFAULT 3, days_max TINYINT UNSIGNED NOT NULL DEFAULT 7,
 active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_shipping_state(state), KEY idx_shipping_cep(cep_start,cep_end), KEY idx_shipping_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE orders ADD shipping_label VARCHAR(80) NULL AFTER shipping,
 ADD shipping_days VARCHAR(20) NULL AFTER shipping_label;
CREATE TABLE IF NOT EXISTS carts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NULL, session_id VARCHAR(64) NULL,
 coupon_id INT UNSIGNED NULL, status ENUM('ativo','convertido','abandonado') NOT NULL DEFAULT 'ativo',
 abandoned_notified_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_carts_user(user_id,status), KEY idx_carts_session(session_id,status), KEY idx_carts_updated(updated_at),
 CONSTRAINT fk_carts_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_carts_coupon FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cart_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cart_id INT UNSIGNED NOT NULL, book_id INT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_cart_book(cart_id,book_id), KEY idx_cart_items_book(book_id),
 CONSTRAINT fk_cart_items_cart FOREIGN KEY(cart_id) REFERENCES carts(id) ON DELETE CASCADE,
 CONSTRAINT fk_cart_items_book FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS reviews (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, book_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
 order_id INT UNSIGNED NULL, rating TINYINT UNSIGNED NOT NULL, title VARCHAR(120) NULL, comment TEXT NULL,
 status ENUM('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente', admin_reply TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_reviews_user_book(user_id,book_id), KEY idx_reviews_book_status(book_id,status), KEY idx_reviews_status(status),
 CONSTRAINT chk_reviews_rating CHECK(rating BETWEEN 1 AND 5),
 CONSTRAINT fk_reviews_book FOREIGN KEY(book_id) REFERENCES books(id) ON DELETE CASCADE,
 CONSTRAINT fk_reviews_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_reviews_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS contact_messages (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NULL, name VARCHAR(100) NOT NULL,
 email VARCHAR(190) NOT NULL, phone VARCHAR(20) NULL, subject VARCHAR(150) NOT NULL, message TEXT NOT NULL,
 status ENUM('nova','lida','respondida','arquivada') NOT NULL DEFAULT 'nova', admin_reply TEXT NULL,
 replied_by INT UNSIGNED NULL, replied_at DATETIME NULL, ip VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_contact_status(status), KEY idx_contact_created(created_at), KEY idx_contact_email(email),
 CONSTRAINT fk_contact_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_contact_replier FOREIGN KEY(replied_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS banners (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, subtitle VARCHAR(255) NULL,
 image VARCHAR(255) NULL, link_url VARCHAR(255) NULL, button_text VARCHAR(40) NULL,
 position ENUM('home_topo','home_meio','home_rodape') NOT NULL DEFAULT 'home_topo', sort_order INT NOT NULL DEFAULT 0,
 starts_at DATETIME NULL, ends_at DATETIME NULL, active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_banners_show(active,position,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `returns` (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
 type ENUM('troca','devolucao') NOT NULL, reason ENUM('defeito','item_errado','arrependimento','outro') NOT NULL,
 description TEXT NULL, status ENUM('solicitada','aprovada','recusada','recebida','concluida') NOT NULL DEFAULT 'solicitada',
 admin_note TEXT NULL, refund_amount DECIMAL(10,2) NULL, resolved_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_returns_order(order_id), KEY idx_returns_user(user_id), KEY idx_returns_status(status),
 CONSTRAINT fk_returns_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_returns_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS return_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, return_id INT UNSIGNED NOT NULL, order_item_id INT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL DEFAULT 1, UNIQUE KEY uq_return_item(return_id,order_item_id), KEY idx_return_items_oi(order_item_id),
 CONSTRAINT fk_return_items_return FOREIGN KEY(return_id) REFERENCES `returns`(id) ON DELETE CASCADE,
 CONSTRAINT fk_return_items_oi FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS coupon_usages (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, coupon_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
 order_id INT UNSIGNED NULL, discount DECIMAL(10,2) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_coupon_user(coupon_id,user_id), KEY idx_coupon_usages_order(order_id), KEY idx_coupon_usages_user(user_id),
 CONSTRAINT fk_cu_coupon FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
 CONSTRAINT fk_cu_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_cu_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO shipping_rates(name,state,price,free_above,days_min,days_max) VALUES
 ('Frete padrão Brasil',NULL,25,250,5,12),('Sudeste - SP','SP',12,150,2,5),('Sudeste - RJ','RJ',12,150,2,5),('Sudeste - MG','MG',15,150,3,6),('Sudeste - ES','ES',15,150,3,6);
INSERT INTO banners(title,subtitle,link_url,button_text,position,sort_order) VALUES
 ('Bem-vindo à livraria','Veja nossos livros em destaque','/catalogo','Ver catálogo','home_topo',1);
INSERT IGNORE INTO settings(setting_key,setting_value) VALUES
 ('reviews_need_approval','1'),('cart_abandoned_hours','24'),('return_window_days','7'),('pix_expires_minutes','60');
CREATE OR REPLACE VIEW v_book_ratings AS SELECT book_id,ROUND(AVG(rating),1) AS rating_avg,COUNT(*) AS rating_count FROM reviews WHERE status='aprovado' GROUP BY book_id;
-- Depende de orders.cancel_requested_at, definido em perfil_usuario.sql.
CREATE OR REPLACE VIEW v_admin_attention AS SELECT
 (SELECT COUNT(*) FROM orders WHERE status='pendente') AS pedidos_pendentes,
 (SELECT COUNT(*) FROM orders WHERE cancel_requested_at IS NOT NULL AND status NOT IN ('cancelado','entregue')) AS cancelamentos_pedidos,
 (SELECT COUNT(*) FROM reviews WHERE status='pendente') AS avaliacoes_pendentes,
 (SELECT COUNT(*) FROM contact_messages WHERE status='nova') AS mensagens_novas,
 (SELECT COUNT(*) FROM `returns` WHERE status='solicitada') AS devolucoes_pendentes,
 (SELECT COUNT(*) FROM v_low_stock) AS estoque_baixo;
