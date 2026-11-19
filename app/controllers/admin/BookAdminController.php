<?php
declare(strict_types=1);

final class BookAdminController
{
    public function index(): void
    {
        require_admin();
        $q = query('q', 100);
        $total=Book::count($q,true); $pages=max(1,(int)ceil($total/25));
        $page=min(max(1,input_int('pagina',$_GET)??1),$pages);
        view('admin/books', [
            'page'=>$page,'pages'=>$pages,'total'=>$total,
            'title' => 'Livros',
            'q'     => $q,
            'books' => Book::search($q, 25, ($page-1)*25, true),
        ], 'admin');
    }

    public function create(): void
    {
        require_admin();
        $this->form(null, ['title' => '', 'author' => '', 'description' => '', 'price' => '', 'stock' => '0', 'featured' => 0, 'image' => null], []);
    }

    public function store(): void
    {
        require_admin();
        [$data, $errors] = $this->validate();

        $uploadError = null;
        $image = $errors ? null : handle_image_upload('image', $uploadError);
        if ($uploadError !== null) {
            $errors['image'] = $uploadError;
        }

        if ($errors) {
            http_response_code(422);
            $this->form(null, $this->formValues($data, null), $errors);
            return;
        }

        $data['image'] = $image;
        try {
            $id = AdminBook::save(null, $data, null);
        } catch (PDOException $e) {
            delete_upload($image);
            if ($e->getCode() !== '23000') throw $e;
            http_response_code(422); $this->form(null, $this->formValues($data, null), ['isbn' => 'Este ISBN já está cadastrado.']); return;
        } catch (Throwable $e) { delete_upload($image); throw $e; }
        flash('success', 'Livro "' . $data['title'] . '" cadastrado.');
        redirect('/admin/livros/' . $id . '/editar');
    }

    public function edit(int $id): void
    {
        require_admin();
        $book = Book::find($id);
        if ($book === null) {
            render_error(404);
            return;
        }
        $this->form($book, [
            'title'       => $book['title'],
            'author'      => $book['author'],
            'description' => $book['description'],
            'price'       => number_format((float) $book['price'], 2, ',', ''),
            'stock'       => (string) $book['stock'],
            'featured'    => (int) $book['featured'],
            'image'       => $book['image'],
        ], []);
    }

    public function update(int $id): void
    {
        require_admin();
        $book = Book::find($id);
        if ($book === null) {
            render_error(404);
            return;
        }

        [$data, $errors] = $this->validate();
        $uploadError = null;
        $newImage = $errors ? null : handle_image_upload('image', $uploadError);
        if ($uploadError !== null) {
            $errors['image'] = $uploadError;
        }

        if ($errors) {
            http_response_code(422);
            $this->form($book, $this->formValues($data, $book['image']), $errors);
            return;
        }

        $oldImage = $book['image'];
        if ($newImage !== null) {
            $data['image'] = $newImage;
        } elseif (($_POST['remove_image'] ?? '') === '1') {
            $data['image'] = null;
        } else {
            $data['image'] = $oldImage;
        }

        try {
            AdminBook::save($id, $data, input_int('expected_stock'));
        } catch (DomainException $e) {
            delete_upload($newImage);
            http_response_code(422); $this->form($book, $this->formValues($data, $book['image']), ['stock' => $e->getMessage()]); return;
        } catch (PDOException $e) {
            delete_upload($newImage);
            if ($e->getCode() !== '23000') throw $e;
            http_response_code(422); $this->form($book, $this->formValues($data, $book['image']), ['isbn' => 'Este ISBN já está cadastrado.']); return;
        } catch (Throwable $e) { delete_upload($newImage); throw $e; }
        if ($oldImage !== $data['image']) {
            delete_upload($oldImage);
        }
        flash('success', 'Livro "' . $data['title'] . '" atualizado.');
        redirect('/admin/livros/' . $id . '/editar');
    }

    public function destroy(int $id): void
    {
        require_admin();
        $book = Book::find($id);
        if ($book === null) {
            flash('error', 'Livro não encontrado.');
            redirect('/admin/livros');
        }
        try { AdminBook::delete($id); }
        catch (DomainException $e) { flash('error', $e->getMessage()); redirect('/admin/livros/' . $id . '/editar'); }
        delete_upload($book['image']);
        flash('success', 'Livro "' . $book['title'] . '" excluído. Os pedidos antigos continuam com o nome e o preço do livro.');
        redirect('/admin/livros');
    }

    /** @return array{0:array,1:array<string,string>} */
    private function validate(): array
    {
        $priceRaw = input('price', 20);
        $stockRaw = input('stock', 10);
        $data = [
            'title'       => input('title', 200),
            'author'      => input('author', 150),
            'description' => input('description', 5000),
            'price_raw'   => $priceRaw,
            'stock_raw'   => $stockRaw,
            'price_cents' => parse_money($priceRaw),
            'stock'       => filter_var($stockRaw, FILTER_VALIDATE_INT),
            'featured'    => ($_POST['featured'] ?? '') === '1' ? 1 : 0,
            'image'       => null,
        ];

        $errors = [];
        if (AdminSchema::core()) {
            $data += [
                'category_id' => input_int('category_id'), 'isbn' => input('isbn', 20) ?: null,
                'publisher' => input('publisher', 120) ?: null, 'pages' => input_int('pages'),
                'cost_price' => input('cost_price', 20) === '' ? null : parse_money(input('cost_price', 20)),
                'min_stock' => input_int('min_stock'), 'active' => ($_POST['active'] ?? '') === '1' ? 1 : 0,
            ];
            if ($data['category_id'] !== null && !db_value('SELECT id FROM categories WHERE id=?', [$data['category_id']])) $errors['category_id'] = 'Escolha uma categoria válida.';
            if ($data['isbn'] !== null) {
                $data['isbn']=strtoupper(str_replace(['-',' '],'',$data['isbn']));
                if (!preg_match('/^(?:[0-9]{13}|[0-9]{9}[0-9X])$/D',$data['isbn'])) $errors['isbn'] = 'Informe um ISBN com 10 ou 13 dígitos (hífens são aceitos).';
            }
            if ($data['pages'] !== null && ($data['pages'] < 1 || $data['pages'] > 65535)) $errors['pages'] = 'Informe entre 1 e 65535 páginas.';
            if (input('pages', 10) !== '' && $data['pages'] === null) $errors['pages'] = 'Informe um número inteiro de páginas.';
            if ($data['cost_price'] === null && input('cost_price', 20) !== '') $errors['cost_price'] = 'Informe um custo válido.';
            if ($data['cost_price'] !== null) $data['cost_price'] = cents_to_decimal($data['cost_price']);
            if ($data['min_stock'] === null || $data['min_stock'] < 0 || $data['min_stock'] > 100000) $errors['min_stock'] = 'Informe um mínimo entre 0 e 100000.';
        }
        $data['stock_note'] = input('stock_note', 255);
        if (text_length($data['title']) < 2) {
            $errors['title'] = 'Informe o título.';
        }
        if (text_length($data['author']) < 2) {
            $errors['author'] = 'Informe o autor.';
        }
        if (text_length($data['description']) < 10) {
            $errors['description'] = 'Escreva uma descrição com pelo menos 10 caracteres.';
        }
        if ($data['price_cents'] === null || $data['price_cents'] <= 0) {
            $errors['price'] = 'Informe um preço válido. Exemplo: 39,90';
        }
        if ($data['stock'] === false || $data['stock'] < 0 || $data['stock'] > 100000) {
            $errors['stock'] = 'Informe o estoque como número inteiro de 0 a 100000.';
        }
        return [$data, $errors];
    }

    private function formValues(array $data, ?string $image): array
    {
        return [
            'title'       => $data['title'],
            'author'      => $data['author'],
            'description' => $data['description'],
            'price'       => $data['price_raw'],
            'stock'       => $data['stock_raw'],
            'featured'    => $data['featured'],
            'image'       => $image,
        ] + array_intersect_key($data, array_flip(['category_id','isbn','publisher','pages','cost_price','min_stock','active','stock_note']));
    }

    private function form(?array $book, array $values, array $errors): void
    {
        $extended = AdminSchema::core();
        $values += ($book ?? []) + ['category_id' => '', 'isbn' => '', 'publisher' => '', 'pages' => '', 'cost_price' => '', 'min_stock' => 3, 'active' => 1, 'stock_note' => ''];
        view('admin/book_form', [
            'title'    => $book === null ? 'Novo livro' : 'Editar livro',
            'book'     => $book,
            'values'   => $values,
            'errors'   => $errors,
            'maxBytes' => (int) config('upload.max_bytes', 2 * 1024 * 1024),
            'extended' => $extended,
            'categories' => $extended ? db_all('SELECT id,name,active FROM categories ORDER BY name') : [],
        ], 'admin');
    }
}
