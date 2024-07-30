
$('body').on('click', '.add-to-cart', function (e) {
    e.preventDefault();
    prod = $(this).parents('.product-box').data('id');
    image = $(this).parents('.product-box').find('img').attr('src');
    name = $(this).parents('.product-box').find('.product-name').text();

    cant = 1;
    if($(this).parents('.product-box').find('.input-quantity').length > 0){
        cant = $(this).parents('.product-box').find('.input-quantity').val();
    }

    addToCart(prod, image, name, cant);
});

function addToCart(prod, image, name, cant){
    blockPage();
    $.post('/tienda-casa-perez/public/carrito/add/', {fk_producto: prod, cantidad: cant},
        function (resp) {
            if(resp.response){
                swal({
                    type: "success",
                    title: "¡Listo!",   
                    text: "Se agregó <strong>"+name+"</strong> al carrito.",   
                    //timer: 5000,
                    html: true,
                    showCancelButton: true,   
                    cancelButtonText: "Seguir comprando",
                    //confirmButtonColor: "#DD6B55",   
                    confirmButtonText: "Ver carrito",
                    closeOnConfirm: false 
                }, function(){
                    window.location.href = '/carrito';
                } );
                
                addProductNotice('Producto agregado al carrito', 
                                '<img src="'+image+'" alt="">', 
                                '<h3><a href="#">'+name+'</a> aregado al <a href="/carrito">carrito</a>!</h3>', 'success');
                addProdtoWidget(resp.item);
                $('.cart .quantity span:eq(0)').html(resp.productos);
                $('.cart .total').html(resp.total);

                if($('.header-box .header-user ul #to-checkout').length == 0) {
                    $('.header-box .header-user ul').append($('<li><a href="/tienda-casa-perez/public/checkout" id="to-checkout" class="smooth"><i class="fa fa-check-square-o"></i> Pagar Ahora</a></li>'));
                }

                $.unblockUI();
            }
        },
        "json"
    );
}

function addProdtoWidget(data){
    $('.cart-table tr[data-prod="'+data.fk_producto+'"]').remove();
    item = $('<tr data-prod="'+data.fk_producto+'"></tr>');
    image = $('<td class="product-item-image"></td>');
    image.append('<a href="/producto/'+data.url+'" class=""><img src="'+data.thumbnail+'" alt="" class="img-responsive"></a>');
    image.append('<button class="remove-product-cart smooth" title="Eliminar del carrito"><i class="fa fa-times"></i></button>');
    prod = $('<td class="product-item-name"></td>');
    prod.append('<h4 class="product-name"><a href="/producto/'+data.url+'" class="smooth">'+data.producto+'</a></h4>');
    prod.append('<span class="product-item-quantity">Cantidad: '+data.cantidad+'</span>');
    precio = $('<td class="product-item-price"></td>');
    precio.append('<span class="shopping-price">$'+data.importe+'</span>');

    item.append(image);
    item.append(prod);
    item.append(precio);

    $('.cart-table').append(item);
    $('.cart-empty').remove();
}

$('body').on('click', '.remove-product-cart', function (e) {
    e.stopPropagation();
    e.preventDefault();
    item = $(this).parents('tr');
    prod = item.data('prod');
    $.post('/tienda-casa-perez/public/carrito/del/', {fk_producto: prod},
        function (resp) {
            if(resp.response){
                item.remove();
                $('.cart .quantity span:eq(0)').html(resp.productos);
                $('.cart .total').html(resp.total);
                $('.cart').removeClass('open');
                $.get('/tienda-casa-perez/public/carrito/getNumProducts/', function(resp) {
                    if(resp.response) {
                        if(resp.result == 0) {
                            $('.header-box .header-user ul li a#to-checkout').remove();
                        }
                    }
                }, 'json');
            }
        },'json'
    );
});

function addProductNotice(title, thumb, text, type) {
    $.jGrowl.defaults.closer = false;
    //Stop jGrowl
    //$.jGrowl.defaults.sticky = true;
    var tpl = thumb + '<h3>'+text+'</h3>';
    $.jGrowl(tpl, {		
        life: 5000,
        header: title,
        speed: 'slow',
        theme: type
    });
}

$('body').on('click', '.btn-comprar', function (e) {
    e.preventDefault();
    prod = $(this).data('prod');
    image = $(this).parents('tr').find('img').attr('src');
    name = $(this).parents('tr').find('td:eq(1)').text();
    cant = 1;
    addToCart(prod, image, name, cant);
});