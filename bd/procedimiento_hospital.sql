DROP PROCEDURE IF EXISTS tbl_hospital;
DELIMITER //
CREATE PROCEDURE tbl_hospital(IN bus VARCHAR(100), IN orden VARCHAR(50), IN inicial INT, IN limite INT, IN tbl_name VARCHAR(50))
BEGIN
    START TRANSACTION;

    SET @drop_query = CONCAT('DROP TEMPORARY TABLE IF EXISTS ', tbl_name);
    PREPARE stmt_drop FROM @drop_query;
    EXECUTE stmt_drop;
    DEALLOCATE PREPARE stmt_drop;

    SET @t1 = CONCAT(
        'CREATE TEMPORARY TABLE ', tbl_name, ' (',
        'id CHAR(10), ',
        'nombre2 CHAR(100), ',
        'nombre VARCHAR(255), ',
        'cantidad INT, ',
        'unidad INT, ',
        'no_ventas INT, ',
        'FULLTEXT(id, nombre2), ',
        'FULLTEXT(nombre2)',
        ') ENGINE=MyISAM'
    );
    PREPARE stmt1 FROM @t1;
    EXECUTE stmt1;
    DEALLOCATE PREPARE stmt1;

    SET @t2 = CONCAT(
        'INSERT INTO ', tbl_name, ' ',
        'SELECT ',
            'CONVERT(producto.id, CHAR(10)) AS id, ',
            'CONVERT(REPLACE(producto.nombre, \'/\', \'_\'), CHAR(100)) AS nombre2, ',
            'producto.nombre, ',
            'producto.stock AS cantidad, ',
            'producto.unidad AS unidad, ',
            'producto.no_ventas AS no_ventas ',
        'FROM producto ',
        'WHERE producto.status = 1 ',
        'AND producto.tipo IN (6, 7, 10) ',
        'GROUP BY producto.id'
    );
    PREPARE stmt2 FROM @t2;
    EXECUTE stmt2;
    DEALLOCATE PREPARE stmt2;

    SET @t3 = CONCAT(
        'SELECT ',
            '*, ',
            'CASE WHEN \'', bus, '\' != \'_\' THEN MATCH(nombre2) AGAINST(\'', bus, '\' IN BOOLEAN MODE) ELSE TRUE END AS prioridad ',
        'FROM ', tbl_name, ' ',
        'WHERE ',
            'CASE WHEN \'', bus, '\' != \'_\' THEN MATCH(id, nombre2) AGAINST(\'', bus, '\' IN BOOLEAN MODE) ELSE TRUE END ',
        'ORDER BY no_ventas DESC, cantidad DESC ',
        'LIMIT ', inicial, ', ', limite
    );
    PREPARE stmt3 FROM @t3;
    EXECUTE stmt3;
    DEALLOCATE PREPARE stmt3;

    COMMIT;

END
//
DELIMITER ;
