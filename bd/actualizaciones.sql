-- 12b8dc41677c10b562a2e3a508b8c007 passcode user 30 (1234)
-- 866b7017f2af9f5c1eeb602eda1cf056 passcode user 1 (4567)
-- b24bd1443dce71049c04faa257573d22 passcode user 5 (8901)
-- 7f2ffef01914c840f248196eeb2f6845 passcode user 16 (0496)
-- 5d60e5aed3f91b73b85c03389dca9882 passcode user 20 (5793)

CREATE TABLE `agenda` (
  `id` int(10) UNSIGNED NOT NULL,
  `producto_id` int(10) UNSIGNED NOT NULL,
  `det_venta_id` int(10) UNSIGNED NOT NULL,
  `farmacia_paquete_id` int(11) NOT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `agenda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agenda_fk_producto` (`producto_id`),
  ADD KEY `agenda_fk_det_venta` (`det_venta_id`),
  ADD KEY `agenda_fk_farmacia_paquete` (`farmacia_paquete_id`);

ALTER TABLE `agenda`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `agenda`
  ADD CONSTRAINT `agenda_fk_det_venta` FOREIGN KEY (`det_venta_id`) REFERENCES `det_venta` (`id`),
  ADD CONSTRAINT `agenda_fk_farmacia_paquete` FOREIGN KEY (`farmacia_paquete_id`) REFERENCES `farmacia_paquete` (`id`),
  ADD CONSTRAINT `agenda_fk_producto` FOREIGN KEY (`producto_id`) REFERENCES `producto` (`id`);

ALTER TABLE `det_receta` ADD `tipo_admin` TINYINT(1) NULL DEFAULT '3' AFTER `duracion`; 