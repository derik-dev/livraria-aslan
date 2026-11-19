<?php
declare(strict_types=1);

final class BookAdminController
{
    public function index(): void
    {
        require_admin();
        $q = query('q', 100);
        view('admin/books', [
            'title' => 'Livros',
            'q'     => $q,
            'books' => Book::search($q, 500, 0),
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
        $id = Book::create($data);
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

        Book::update($id, $data);
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
        Book::delete($id);
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
        ];
    }

    private function form(?array $book, array $values, array $errors): void
    {
        view('admin/book_form', [
            'title'    => $book === null ? 'Novo livro' : 'Editar livro',
            'book'     => $book,
            'values'   => $values,
            'errors'   => $errors,
            'maxBytes' => (int) config('upload.max_bytes', 2 * 1024 * 1024),
        ], 'admin');
    }
}
