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
$router->post('/pedidos/{id}/devolucao', [AccountController::class, 'requestReturn']);

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
$router->post('/admin/pedidos/{id}/dados', [OrderAdminController::class, 'metadata']);
$router->post('/admin/pedidos/{id}/cancelamento', [OrderAdminController::class, 'cancellation']);
$router->post('/admin/pedidos/{id}/fatura', [OrderAdminController::class, 'invoice']);
$router->get('/admin/dedicatorias', [DedicationAdminController::class, 'index']);

$router->get('/admin/clientes', [CustomerAdminController::class, 'index']);
$router->get('/admin/clientes/{id}', [CustomerAdminController::class, 'show']);
$router->post('/admin/clientes/{id}/situacao', [CustomerAdminController::class, 'toggle']);

foreach (array_keys(AdminResources::definitions()) as $area) {
    $router->get('/admin/' . $area, [ResourceAdminController::class, 'index']);
    $router->get('/admin/' . $area . '/novo', [ResourceAdminController::class, 'create']);
    $router->post('/admin/' . $area, [ResourceAdminController::class, 'store']);
    $router->get('/admin/' . $area . '/{id}/editar', [ResourceAdminController::class, 'edit']);
    $router->post('/admin/' . $area . '/{id}', [ResourceAdminController::class, 'update']);
    $router->post('/admin/' . $area . '/{id}/excluir', [ResourceAdminController::class, 'destroy']);
}
$router->get('/admin/estoque', [PanelAdminController::class, 'stock']);
$router->post('/admin/estoque', [PanelAdminController::class, 'moveStock']);
$router->get('/admin/configuracoes', [PanelAdminController::class, 'settings']);
$router->post('/admin/configuracoes', [PanelAdminController::class, 'settings']);
$router->get('/admin/seguranca', [PanelAdminController::class, 'security']);
foreach (array_keys(AdminWorkflows::definitions()) as $area) {
    $router->get('/admin/'.$area, [WorkflowAdminController::class, 'index']);
    $router->get('/admin/'.$area.'/{id}', [WorkflowAdminController::class, 'show']);
    $router->post('/admin/'.$area.'/{id}', [WorkflowAdminController::class, 'update']);
    $router->post('/admin/'.$area.'/{id}/excluir', [WorkflowAdminController::class, 'destroy']);
}
$router->post('/admin/pagamentos', [WorkflowAdminController::class, 'storePayment']);
$router->get('/admin/pagamentos/{id}/comprovante', [WorkflowAdminController::class, 'proof']);
$router->post('/admin/pagamentos/{id}/comprovante', [WorkflowAdminController::class, 'uploadProof']);
