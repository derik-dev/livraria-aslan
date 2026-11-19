<?php
declare(strict_types=1);

// Banco descartável LOCAL. Nunca lê app/config.php e nunca usa credenciais de produção.
if (PHP_SAPI !== 'cli' || !in_array('--reset-test-database',$argv,true)) exit("Use PHP CLI e --reset-test-database.\n");
$port=(int)(getenv('LIVRARIA_TEST_PORT')?:33079);
$pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP DATABASE IF EXISTS aslan_admin_test');
$pdo->exec('CREATE DATABASE IF NOT EXISTS aslan_admin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE aslan_admin_test');
define('APP_ROOT',dirname(__DIR__));
foreach (['schema.sql','seed.sql','admin.sql'] as $file) $pdo->exec(file_get_contents(APP_ROOT.'/database/'.$file));
$modules=file_get_contents(APP_ROOT.'/database/modulos.sql');
$hasProfile=is_file(APP_ROOT.'/database/perfil_usuario.sql');
if ($hasProfile) $pdo->exec(file_get_contents(APP_ROOT.'/database/perfil_usuario.sql'));
try {
    $pdo->exec($modules);
} catch (PDOException $e) {
    if ($hasProfile || !str_contains($e->getMessage(),'cancel_requested_at')) throw $e;
    echo "CONFIRMADO: modulos.sql depende de orders.cancel_requested_at (perfil_usuario.sql ausente).\n";
    // Fixture só de teste. Não é uma migração nem uma definição presumida do perfil real.
    $pdo->exec('ALTER TABLE orders ADD cancel_requested_at DATETIME NULL');
    $pdo->exec(substr($modules,strpos($modules,'CREATE OR REPLACE VIEW v_admin_attention')));
}
$config=['app_name'=>'Livraria de teste','app_url'=>'http://127.0.0.1:8079','base_path'=>'','debug'=>false,'timezone'=>'America/Sao_Paulo',
    'db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>'aslan_admin_test','user'=>'root','pass'=>'','charset'=>'utf8mb4'],
    'shop'=>['shipping_flat'=>15,'free_shipping_from'=>150,'low_stock'=>3,'per_page'=>12,'max_qty_per_item'=>99],
    'payment'=>['enabled'=>['pix_manual'],'pix'=>['key'=>'teste@local.test','beneficiary'=>'Loja de teste']],
    'upload'=>['max_bytes'=>2097152], 'mail'=>['from'=>'','from_name'=>'Teste']];
file_put_contents(APP_ROOT.'/.tools/config.php',"<?php\nreturn ".var_export($config,true).";\n");
$GLOBALS['__config']=$config;
require APP_ROOT.'/app/helpers.php'; require APP_ROOT.'/app/db.php';
spl_autoload_register(static function($class): void { foreach(['models','controllers','controllers/admin','payment'] as $dir) { $file=APP_ROOT.'/app/'.$dir.'/'.$class.'.php'; if(is_file($file)) { require $file; return; } } });
$GLOBALS['__settings_ready']=true;
date_default_timezone_set('America/Sao_Paulo');
$_SESSION=['user_id'=>1]; $_SERVER['REMOTE_ADDR']='127.0.0.1';
$count=0;
function check(bool $condition,string $message): void { global $count; if(!$condition) throw new RuntimeException($message); $count++; echo "OK $message\n"; }
function rejects(callable $call,string $message): void { try { $call(); } catch(DomainException $e) { check(true,$message); return; } throw new RuntimeException($message); }
check(AdminSchema::core(),'Extensão principal instalada');
$before=(int)Book::find(1)['stock'];
AdminStock::move(1,'entrada',5,'Reposição de teste');
check((int)Book::find(1)['stock']===$before+5,'Entrada aumenta estoque');
rejects(fn()=>AdminStock::move(1,'saida',100000,'Saída inválida'),'Saída não permite estoque negativo');
check((int)Book::find(1)['stock']===$before+5,'Saída inválida não altera estoque');
AdminStock::move(1,'ajuste',12,'Contagem física');
check((int)Book::find(1)['stock']===12,'Ajuste define estoque final');
[$values,$errors]=AdminResources::validate(AdminResources::get('cupons'),['code'=>'TESTE','type'=>'percent','value'=>'101','min_order'=>'0','active'=>'1']);
check(isset($errors['value']),'Cupom acima de 100% é rejeitado');
[$values,$errors]=AdminResources::validate(AdminResources::get('frete'),['name'=>'Faixa','state'=>'SP','cep_start'=>'99999-999','cep_end'=>'01000-000','price'=>'12,50','days_min'=>'7','days_max'=>'2','active'=>'1']);
check(isset($errors['cep_end'],$errors['days_max']),'Frete valida limites de CEP e prazo');
check(!AdminResources::safeLink('javascript:alert(1)') && !AdminResources::safeLink('//example.com') && AdminResources::safeLink('/catalogo'),'Link de banner bloqueia scripts e URLs ambíguas');
$ship=['name'=>'Cliente Teste','phone'=>'11999990000','cep'=>'01001-000','street'=>'Rua Teste','number'=>'10','complement'=>'','district'=>'Centro','city'=>'São Paulo','state'=>'SP'];
$id=Order::create(2,[1=>2],$ship,'pix_manual');
check((int)Book::find(1)['stock']===10,'Pedido baixa estoque');
check((int)db_value("SELECT COUNT(*) FROM stock_movements WHERE type='venda' AND book_id=1")===1,'Venda gera histórico de estoque');
$payment=(int)db_value('SELECT id FROM payments WHERE order_id=?',[$id]);
check($payment>0,'Checkout gera tentativa de pagamento');
AdminPayments::change($payment,'aprovar','Recebimento conferido');
check(Order::find($id)['status']==='pago','Aprovação do valor integral marca pedido pago');
rejects(fn()=>AdminPayments::change($payment,'aprovar','Repetido'),'Aprovação duplicada é rejeitada');
check((int)db_value("SELECT COUNT(*) FROM order_status_history WHERE order_id=? AND new_status='pago'",[$id])===1,'Aprovação registra uma única etapa');
Order::changeStatus($id,'enviado','Postagem'); Order::changeStatus($id,'entregue','Entrega');
rejects(fn()=>Order::changeStatus($id,'pendente'),'Status não retrocede após entrega');
AdminPayments::change($payment,'estornar','Estorno parcial conferido',1000);
check(to_cents(db_value('SELECT refund_amount FROM payments WHERE id=?',[$payment]))===1000,'Estorno parcial registra valor acumulado');
rejects(fn()=>AdminPayments::change($payment,'estornar','Valor inválido',999999),'Estorno não ultrapassa o saldo');
$cancel=Order::create(2,[1=>1],$ship,'pix_manual');
Order::changeStatus($cancel,'cancelado','Pedido cancelado');
Order::changeStatus($cancel,'cancelado','Repetição');
check((int)Book::find(1)['stock']===10,'Cancelamento repetido repõe estoque uma única vez');
rejects(fn()=>Order::changeStatus($cancel,'pago'),'Pedido cancelado não reabre');
db_run('UPDATE books SET active=0 WHERE id=2');
check(count(Book::findMany([2]))===0 && count(Book::search('Cartas',10,0))===0,'Livro inativo sai do carrinho e busca pública');
check(count(Book::search('Cartas',10,0,true))===1,'Admin encontra livro inativo');
rejects(fn()=>Order::create(2,[2=>1],$ship,'pix_manual'),'Compra direta de livro inativo é rejeitada');
$activeUser=AdminLogin::authenticate('admin@livraria.test','Admin@2026',true);
check($activeUser['user']!==null,'Admin autentica com credenciais do seed');
for($i=0;$i<5;$i++) AdminLogin::authenticate('cliente@livraria.test','errada');
check(AdminLogin::authenticate('cliente@livraria.test','Cliente@2026')['blocked'],'Bloqueio após cinco senhas incorretas');
db_run('DELETE FROM login_attempts WHERE email=?',['cliente@livraria.test']);
db_run('UPDATE users SET active=0 WHERE id=2');
check(AdminLogin::authenticate('cliente@livraria.test','Cliente@2026')['user']===null,'Cliente bloqueado não faz login');
db_run('UPDATE users SET active=1 WHERE id=2');
$sales=AdminReports::sales(date('Y-m-d'),date('Y-m-d'));
check($sales['count']===1,'Dashboard ignora pedidos cancelados e pendentes');
check((int)db_value('SELECT COUNT(*) FROM audit_logs')>0,'Operações geram auditoria');
// Solicitação e itens reais para verificar reposição e reembolso transacionais.
db_run("INSERT INTO `returns`(order_id,user_id,type,reason) VALUES (?,2,'devolucao','arrependimento')",[$id]);
$returnId=(int)db()->lastInsertId(); $itemId=(int)db_value('SELECT id FROM order_items WHERE order_id=?',[$id]);
db_run('INSERT INTO return_items(return_id,order_item_id,quantity) VALUES (?,?,1)',[$returnId,$itemId]);
AdminWorkflows::returnsUpdate($returnId,'aprovada','Conferido',null,0,false,'solicitada');
AdminWorkflows::returnsUpdate($returnId,'recebida','Livro recebido',null,0,true,'aprovada');
check((int)Book::find(1)['stock']===11,'Recebimento de devolução repõe somente com confirmação');
rejects(fn()=>AdminWorkflows::returnsUpdate($returnId,'recebida','Repetição',null,0,true,'aprovada'),'Devolução não duplica reposição');
AdminWorkflows::returnsUpdate($returnId,'concluida','Reembolso conferido',2000,$payment,false,'recebida');
check(to_cents(db_value('SELECT refund_amount FROM payments WHERE id=?',[$payment]))===3000,'Reembolso de devolução usa saldo do pagamento');
db_run("INSERT INTO reviews(book_id,user_id,order_id,rating,title,comment) VALUES (1,2,?,5,'Muito bom','Avaliação de teste')",[$id]);
db_run("INSERT INTO contact_messages(user_id,name,email,subject,message) VALUES (2,'Cliente Teste','cliente@local.test','Dúvida','Mensagem de teste')");
Order::create(2,[3=>1],$ship,'pix_manual');
$cancelRequest=Order::create(2,[3=>1],$ship,'pix_manual');
db_run('UPDATE orders SET cancel_requested_at=NOW() WHERE id=?',[$cancelRequest]);
$cancelApprove=Order::create(2,[3=>1],$ship,'pix_manual');
db_run('UPDATE orders SET cancel_requested_at=NOW() WHERE id=?',[$cancelApprove]);
echo "\n$count verificações passaram. Banco local: aslan_admin_test.\n";
