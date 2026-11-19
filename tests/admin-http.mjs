// Requer o servidor local com tests/admin-router.php e o banco dos testes PHP.
import assert from 'node:assert/strict';
const base='http://127.0.0.1:8079/livraria-eric-aslan';
let cookie=''; let count=0;
async function request(path, fields=null, method='GET') {
  const headers={}; if(cookie)headers.Cookie=cookie;
  let body;
  if(fields instanceof FormData) body=fields;
  else if(fields!==null){ body=new URLSearchParams(fields);headers['Content-Type']='application/x-www-form-urlencoded'; }
  const response=await fetch(base+path,{method:fields!==null?'POST':method,headers,body,redirect:'manual'});
  const cookies=response.headers.getSetCookie();
  if(cookies.length)cookie=cookies.map(v=>v.split(';')[0]).join('; ');
  const html=await response.text();
  return {status:response.status,html,location:response.headers.get('Location')};
}
function check(test,message){assert.ok(test,message);count++;console.log('OK '+message);}
function csrf(html){const token=html.match(/name="_csrf" value="([a-f0-9]+)"/);assert.ok(token,'Token CSRF presente');return token[1];}
async function login(email,password,path='/admin/login'){
 const page=await request(path);return request(path,{_csrf:csrf(page.html),email,password});
}
let r=await request('/admin');check(r.status===303&&r.location==='/livraria-eric-aslan/admin/login','Admin anônimo redireciona na subpasta correta');
r=await login('admin@livraria.test','Admin@2026');check(r.status===303&&r.location==='/livraria-eric-aslan/admin','Login admin preserva subpasta');
const paths=['/admin','/admin/livros','/admin/livros/novo','/admin/livros/1/editar','/admin/categorias','/admin/categorias/novo','/admin/categorias/1/editar','/admin/estoque','/admin/pedidos','/admin/pedidos/1','/admin/pagamentos','/admin/pagamentos/1','/admin/frete','/admin/frete/novo','/admin/frete/1/editar','/admin/cupons','/admin/cupons/novo','/admin/cupons/1/editar','/admin/clientes','/admin/clientes/2','/admin/avaliacoes','/admin/avaliacoes/1','/admin/mensagens','/admin/mensagens/1','/admin/devolucoes','/admin/devolucoes/1','/admin/banners','/admin/banners/novo','/admin/banners/1/editar','/admin/configuracoes','/admin/seguranca','/admin/seguranca?tipo=login'];
for(const path of paths){r=await request(path);check(r.status===200&&!r.html.includes('<h1>Erro no servidor</h1>'),`Tela ${path} responde sem erro`);check(!/\b(?:href|action)="\/admin/.test(r.html),`Links de ${path} preservam subpasta`);}
r=await request('/admin/categorias');let token=csrf(r.html);
r=await request('/admin/categorias',{_csrf:token,name:'Teste HTTP',slug:'teste-http',active:'1'});check(r.status===303,'Categoria cadastrada pelo formulário');
let location=r.location.replace('/livraria-eric-aslan','');r=await request(location);check(r.html.includes('Teste HTTP'),'Categoria gravada aparece no formulário');
r=await request(location.replace('/editar',''),{_csrf:csrf(r.html),name:'Categoria desativada',slug:'teste-http'});check(r.status===303,'Categoria pode ser desativada');
r=await request('/admin/cupons');token=csrf(r.html);
r=await request('/admin/cupons',{_csrf:token,code:'INVALIDO',type:'percent',value:'101',min_order:'0',active:'1'});check(r.status===422&&r.html.includes('100%'),'Validação de cupom aparece na tela');
r=await request('/admin/estoque');r=await request('/admin/estoque',{_csrf:csrf(r.html),book_id:'3',type:'entrada',quantity:'2',note:'Reposição via HTTP'});check(r.status===303,'Movimentação via formulário registrada');
r=await request('/admin/estoque');check(r.html.includes('Reposição via HTTP'),'Histórico mostra motivo da movimentação');
r=await request('/admin/banners/novo');r=await request('/admin/banners',{_csrf:csrf(r.html),title:'Banner de teste',subtitle:'Sem imagem também funciona',link_url:'/catalogo',button_text:'Ver livros',position:'home_topo',sort_order:'2',starts_at:'2026-11-01T08:00',ends_at:'2026-12-01T08:00',active:'1'});check(r.status===303,'Banner pode ser criado e agendado sem imagem obrigatória');
location=r.location.replace('/livraria-eric-aslan','');r=await request(location);check(r.html.includes('Banner de teste')&&r.html.includes('2026-12-01T08:00'),'Agendamento do banner persiste');
r=await request('/admin/frete/novo');r=await request('/admin/frete',{_csrf:csrf(r.html),name:'Faixa HTTP',state:'SP',cep_start:'01000-000',cep_end:'01999-999',price:'9,90',free_above:'100',days_min:'2',days_max:'4',active:'1'});check(r.status===303,'Faixa de frete por CEP pode ser cadastrada');
location=r.location.replace('/livraria-eric-aslan','');r=await request(location);check(r.html.includes('01000000')&&r.html.includes('01999999'),'Faixa de CEP persiste normalizada');
r=await request('/admin/cupons/novo');r=await request('/admin/cupons',{_csrf:csrf(r.html),code:'HTTP10',type:'fixed',value:'10',min_order:'50',max_uses:'20',active:'1'});check(r.status===303,'Cupom de valor fixo pode ser cadastrado');
r=await request('/admin/livros/novo');r=await request('/admin/livros',{_csrf:csrf(r.html),title:'Livro criado pelo admin',author:'Autor Teste',description:'Descrição de um livro cadastrado pelo formulário de teste.',price:'39,90',stock:'4',category_id:'1',isbn:'978-0-306-40615-7',publisher:'Editora Teste',pages:'120',cost_price:'20',min_stock:'2',active:'1',featured:'1',stock_note:'Estoque inicial'});check(r.status===303,'Livro com metadados completos pode ser cadastrado');
location=r.location.replace('/livraria-eric-aslan','');r=await request(location);check(r.html.includes('9780306406157')&&r.html.includes('Editora Teste'),'ISBN normalizado e editora persistem');
const bookAction=location.replace('/editar','');
r=await request(bookAction,{_csrf:csrf(r.html),expected_stock:'4',title:'Livro editado pelo admin',author:'Autor Teste',description:'Descrição de um livro atualizado pelo formulário de teste.',price:'44,90',stock:'4',category_id:'1',isbn:'9780306406157',publisher:'Editora Teste',pages:'120',cost_price:'20',min_stock:'2',featured:'1'});check(r.status===303,'Livro pode ser editado e desativado');
r=await request('/catalogo?q=Livro+editado+pelo+admin');check(!r.html.includes('<strong>Livro editado pelo admin</strong>')&&!r.html.includes('alt="Capa de Livro editado pelo admin"'),'Livro desativado não aparece no catálogo');
r=await request('/admin/configuracoes');r=await request('/admin/configuracoes',{_csrf:csrf(r.html),store_name:'Livraria HTTP',store_email:'contato@local.test',store_phone:'11999990000',store_whatsapp:'11999990000',pix_key:'pix@local.test',pix_holder:'Titular de teste',pix_expires_minutes:'60',shipping_fixed:'17,50',free_shipping_min:'200',return_window_days:'7',cart_abandoned_hours:'24',reviews_need_approval:'1'});check(r.status===303,'Configurações da loja podem ser salvas');
r=await request('/admin');check(r.html.includes('Livraria HTTP'),'Nome configurado é usado pelo painel');
r=await request('/admin/clientes/2');r=await request('/admin/clientes/2/situacao',{_csrf:csrf(r.html),active:'0'});check(r.status===303,'Admin bloqueia cliente');
r=await request('/admin/clientes/2');check(r.html.includes('Cliente bloqueado'),'Situação bloqueada aparece no detalhe');
r=await request('/admin/clientes/2/situacao',{_csrf:csrf(r.html),active:'1'});check(r.status===303,'Admin desbloqueia cliente');
r=await request('/admin/avaliacoes/1');r=await request('/admin/avaliacoes/1',{_csrf:csrf(r.html),expected_status:'pendente',status:'aprovado',admin_reply:'Obrigado pela avaliação!'});check(r.status===303,'Avaliação pode ser aprovada e respondida');
r=await request('/admin/avaliacoes/1');check(r.html.includes('Obrigado pela avaliação!')&&r.html.includes('Situação: Aprovado'),'Moderação persiste');
r=await request('/admin/mensagens/1');r=await request('/admin/mensagens/1',{_csrf:csrf(r.html),expected_status:'nova',status:'lida',admin_reply:''});check(r.status===303,'Mensagem pode ser marcada como lida');
r=await request('/admin/pedidos/3');r=await request('/admin/pedidos/3/status',{_csrf:csrf(r.html),expected_status:'pendente',status:'pago',status_note:'Conferido no banco'});check(r.status===303,'Admin confirma pedido pendente');
r=await request('/admin/pedidos/3');check(r.html.includes('Atual: <span class="status status-pago">'),'Status de pagamento persiste no pedido');
let hash=r.html.match(/name="expected_metadata" value="([a-f0-9]+)"/)[1];
r=await request('/admin/pedidos/3/dados',{_csrf:csrf(r.html),expected_metadata:hash,tracking_code:'BR123456789BR',admin_note:'Nota interna de teste'});check(r.status===303,'Rastreio e nota interna podem ser salvos');
r=await request('/admin/pedidos/3');check(r.html.includes('BR123456789BR')&&r.html.includes('Nota interna de teste'),'Dados internos persistem no admin');
r=await request('/admin/pedidos/4');let requested=r.html.match(/name="expected_request" value="([^"]+)"/)[1];
r=await request('/admin/pedidos/4/cancelamento',{_csrf:csrf(r.html),expected_request:requested,decision:'recusar',note:'Cliente confirmou que deseja manter o pedido'});check(r.status===303,'Solicitação de cancelamento pode ser recusada');
r=await request('/admin/pedidos/4');check(r.html.includes('Cancelamento recusado:')&&!r.html.includes('name="expected_request"'),'Recusa encerra solicitação e preserva histórico');
r=await request('/admin/pedidos/5');requested=r.html.match(/name="expected_request" value="([^"]+)"/)[1];
r=await request('/admin/pedidos/5/cancelamento',{_csrf:csrf(r.html),expected_request:requested,decision:'aprovar',note:'Cliente desistiu'});check(r.status===303,'Solicitação de cancelamento pode ser aprovada');
r=await request('/admin/pedidos/5');check(r.html.includes('Atual: <span class="status status-cancelado">'),'Aprovação cancela o pedido');
r=await request('/admin/pagamentos/1');let form=new FormData();form.set('_csrf',csrf(r.html));form.set('proof',new Blob(['%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'],{type:'application/pdf'}),'comprovante.pdf');
r=await request('/admin/pagamentos/1/comprovante',form);check(r.status===303,'Comprovante PDF pode ser anexado');
r=await request('/admin/pagamentos/1/comprovante');check(r.status===200&&r.html.startsWith('%PDF'),'Comprovante pode ser baixado por admin');
r=await request('/admin/estoque',{book_id:'1',type:'saida',quantity:'1',note:'Sem token'});check(r.status===419,'POST sem CSRF é recusado');
cookie='';r=await request('/admin/pagamentos/1/comprovante');check(r.status===303,'Comprovante exige autenticação');
r=await login('cliente@livraria.test','Cliente@2026','/login');check(r.status===303,'Cliente faz login pela loja');
for(const path of ['/admin','/admin/estoque','/admin/pagamentos/1/comprovante','/admin/configuracoes','/admin/seguranca']){r=await request(path);check(r.status===403,`Cliente não acessa ${path}`);}
console.log(`\n${count} verificações HTTP passaram.`);
