CREATE TABLE `dictionary` (
  `id` int(11) NOT NULL,
  `fecharegistro` datetime NOT NULL DEFAULT current_timestamp(),
  `palabra` varchar(500) NOT NULL,
  `positiva` varchar(25) NOT NULL,
  `negativa` varchar(25) NOT NULL,
  `neutral` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `dictionary`
  ADD PRIMARY KEY (`id`);
  
ALTER TABLE `dictionary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;