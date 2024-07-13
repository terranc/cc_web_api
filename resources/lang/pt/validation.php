<?php
return [
    'unique'               => ': Atributo existia',
    'accepted'             => ': Atributo é aceito',
    'active_url'           => ': Atributo deve ser um URL legal',
    'after'                => ': O atributo deve ser: uma data após data',
    'alpha'                => ': O atributo deve ser composto de caráter de cartas.',
    'alpha_dash'           => ': O atributo deve ser composto de letras, números, linhas de escrúpulos médios ou caracteres de linha inferior',
    'alpha_num'            => ': O atributo deve ser composto de letras e números',
    'array'                => ': O atributo deve ser uma matriz',
    'before'               => ': Atributo deve ser: uma data antes da data',
    'between'              => [
        'numeric' => ': atributo deve',
        'file'    => ': Atributo deve estar entre: min para: max kb',
        'string'  => ': O atributo deve estar entre: min: personagens máximos',
        'array'   => ': Atributo deve estar entre: min: item máximo',
    ],
    'boolean'              => ': O personagem do atributo deve ser verdadeiro ou falso',
    'confirmed'            => ': A confirmação do atributo não está correspondendo',
    'date'                 => ': O atributo deve ser uma data legal',
    'date_format'          => ': Atributo e um determinado formato: o formato não atende',
    'different'            => ': Atributo deve ser diferente de: Outro',
    'digits'               => ': O atributo deve ser: bit de dígitos',
    'digits_between'       => ': Atributo deve estar entre: min: bit max',
    'email'                => ': O atributo deve ser um endereço de email legal.',
    'filled'               => ': Os campos de atributo são necessários',
    'exists'               => 'Selecionado: o atributo é inválido',
    'image'                => ': O atributo deve ser uma imagem (jpeg, png, bmp ou gif)',
    'in'                   => 'Selecionado: o atributo é inválido',
    'integer'              => ': Atributo deve ser um número inteiro',
    'ip'                   => ': O atributo deve ser um endereço IP legal.',
    'max'                  => [
        'numeric' => ': O comprimento máximo do atributo é: bit máximo',
        'file'    => ': O máximo de atributo é: max',
        'string'  => ': O comprimento máximo do atributo é: personagem máximo',
        'array'   => ': O número máximo de atributo é: max sozinho',
    ],
    'mimes'                => ': O tipo de arquivo do atributo deve ser: valores',
    'min'                  => [
        'numeric' => ': O comprimento mínimo do atributo é: Min Bit',
        'string'  => ': O comprimento mínimo do atributo é: Min Facils',
        'file'    => ': Tamanho do atributo pelo menos: min kb',
        'array'   => ': Atributo pelo menos: Min Item',
    ],
    'not_in'               => 'Selecionado: o atributo é inválido',
    'numeric'              => ': O atributo deve ser números',
    'regex'                => ': O formato de atributo é inválido',
    'required'             => ': O campo de atributo deve ser preenchido',
    'required_if'          => ': O campo de atributo é necessário como: outro é: valor',
    'required_with'        => ': O campo de atributo é necessário como: os valores existiam',
    'required_with_all'    => ': O campo de atributo é necessário como: os valores existiam',
    'required_without'     => ': O campo de atributo é necessário como: os valores não existem',
    'required_without_all' => ': O campo de atributo é necessário. Não há ninguém: os valores existiam',
    'same'                 => ': Atributo e: outro deve combinar',
    'size'                 => [
        'numeric' => ': O atributo deve ser: bit de tamanho',
        'file'    => ': Atributo deve ser: tamanho KB',
        'string'  => ': O atributo deve ser: caracteres de tamanho',
        'array'   => ': O atributo deve incluir: item de tamanho',
    ],
    'url'                  => ': Atributo formato inválido',
    'timezone'             => ': O atributo deve ser um fuso horário eficaz',
    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */
    'custom'               => [
        'attribute-name' => [
            'rule-name' => 'mensagem personalizada',
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */
    'attributes'           => [
        'username' => 'nome de usuário',
        'account'  => 'conta',
        'captcha'  => 'Código de verificação',
        'mobile'   => 'Número de telefone',
        'password' => 'senha',
        'content'  => 'contente',
        'identity' => 'Número de telefone celular/nome de usuário',
    ],
];
