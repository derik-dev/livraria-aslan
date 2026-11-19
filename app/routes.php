<?php
declare(strict_types=1);

/** @var Router $router */

// Loja
$router->get('/', [HomeController::class, 'index']);
$router->get('/catalogo', [HomeController::class, 'catalog']);
$router->get('/livro/{id}', [HomeController::class, 'show']);
$router->get('/api/busca', [SearchController::class, 'suggest']);

// Carrinho e checkout
$router->get('/carrinho', [CartController::class, 'index']);
$router->post('/carrinho/adicionar', [CartController::class, 'add']);
$router->post('/carrinho/atualizar', [CartController::class, 'update']);
$router->post('/carrinho/remover', [CartController::class, 'remove']);
$router->get('/checkout', [CheckoutController::class, 'show']);
$router->post('/checkout', [CheckoutController::class, 'place']);
$router->get('/pedido/{id}/confirmacao', [CheckoutController::class, 'confirmation']);

// Conta
$router->get('/cadastro', [AuthController::class, 'showRegister']);
$router->post('/cadastro', [AuthController::class, 'register']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/recuperar-senha', [AuthController::class, 'showForgot']);
$router->post('/recuperar-senha', [AuthController::class, 'forgot']);
$router->get('/redefinir-senha', [AuthController::class, 'showReset']);
$router->post('/redefinir-senha', [AuthController::class, 'reset']);

$router->get('/perfil', [AccountController::class, 'profile']);
$router->post('/perfil/dados', [AccountController::class, 'updateProfile']);
$router->post('/perfil/endereco', [AccountController::class, 'updateAddress']);
$router->post('/perfil/senha', [AccountController::class, 'updatePassword']);
$router->get('/pedidos', [AccountController::class, 'orders']);
$router->get('/pedidos/{id}', [AccountController::class, 'orderShow']);

// Admin
$router->get('/admin', [DashboardController::class, 'index']);
$router->get('/admin/login', [AdminAuthController::class, 'showLogin']);
$router->post('/admin/login', [AdminAuthController::class, 'login']);
$router->post('/admin/logout', [AdminAuthController::class, 'logout']);

$router->get('/admin/livros', [BookAdminController::class, 'index']);
$router->get('/admin/livros/novo', [BookAdminController::class, 'create']);
$router->post('/admin/livros', [BookAdminController::class, 'store']);
$router->get('/admin/livros/{id}/editar', [BookAdminController::class, 'edit']);
$router->post('/admin/livros/{id}', [BookAdminController::class, 'update']);
$router->post('/admin/livros/{id}/excluir', [BookAdminController::class, 'destroy']);

$router->get('/admin/pedidos', [OrderAdminController::class, 'index']);
$router->get('/admin/pedidos/{id}', [OrderAdminController::class, 'show']);
$router->post('/admin/pedidos/{id}/status', [OrderAdminController::class, 'updateStatus']);

$router->get('/admin/clientes', [CustomerAdminController::class, 'index']);
