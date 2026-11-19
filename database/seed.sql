-- Dados iniciais. Importe depois do schema.sql.
-- Logins de teste:
--   admin@livraria.test   / Admin@2026
--   cliente@livraria.test / Cliente@2026
-- Troque as senhas depois do primeiro acesso.

SET NAMES utf8mb4;

INSERT INTO users (id, name, email, password_hash, phone, role) VALUES
(1, 'Administrador', 'admin@livraria.test',   '$2y$12$2tX9FrTFwXnBN0luCOTLZ.sIsM3xhWLqgZe0/7MHcno9oLF93P96a', NULL,          'admin'),
(2, 'Cliente Teste', 'cliente@livraria.test', '$2y$12$y5CfwtomlYQ0LrPxoPXrHeBDjf7B2lzj2/hbtEJjEsRrWW.osimiG', '11999990000', 'customer');

INSERT INTO addresses (user_id, cep, street, number, complement, district, city, state) VALUES
(2, '01001-000', 'Praça da Sé', '100', 'Apto 12', 'Sé', 'São Paulo', 'SP');

INSERT INTO books (id, title, author, description, price, stock, image, featured) VALUES
(1, 'O Farol de Vidro', 'Helena Duarte Vasconcelos',
 'Numa ilha sem nome, a última guardiã de um farol feito de vidro descobre que a luz que ela acende todas as noites não guia navios, e sim lembranças. Um romance curto sobre memória, solidão e escolhas.',
 39.90, 12, 'assets/img/capa-1.svg', 1),
(2, 'Cartas para o Inverno', 'Rafael Moreno Tavares',
 'Durante um inverno inteiro, dois desconhecidos trocam cartas deixadas dentro de livros de uma biblioteca pública. Cada carta muda um pouco a vida de quem a encontra.',
 54.90, 3, 'assets/img/capa-2.svg', 1),
(3, 'A Cidade dos Relógios Parados', 'Clarice Antunes Rocha',
 'Em uma cidade onde todos os relógios pararam ao mesmo tempo, uma jovem relojoeira precisa descobrir o que aconteceu antes que o tempo pare também para as pessoas.',
 47.50, 25, 'assets/img/capa-3.svg', 1);
