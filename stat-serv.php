<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ==============================================
// CONFIGURACIÓN PROXMOX
// ==============================================
$proxmox_host = 'https://192.168.248.9:8006';
$proxmox_user = 'root@pam';
$proxmox_password = 'xokorro';
$proxmox_verify_ssl = false;

// ==============================================
// FUNCIONES PRINCIPALES
// ==============================================
function getProxmoxTicket($host, $user, $password) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$host/api2/json/access/ticket");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'username' => $user,
        'password' => $password
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $GLOBALS['proxmox_verify_ssl']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $GLOBALS['proxmox_verify_ssl'] ? 2 : 0);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['error' => 'Error de conexión: ' . curl_error($ch)];
    }
    
    $data = json_decode($response, true);
    curl_close($ch);
    
    return $data['data'] ?? ['error' => 'Error de autenticación'];
}

function proxmoxAPI($path, $method = 'GET', $data = []) {
    static $ticket = null;
    static $csrf = null;
    
    if ($ticket === null) {
        $auth = getProxmoxTicket($GLOBALS['proxmox_host'], $GLOBALS['proxmox_user'], $GLOBALS['proxmox_password']);
        if (isset($auth['error'])) return $auth;
        $ticket = $auth['ticket'];
        $csrf = $auth['CSRFPreventionToken'];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $GLOBALS['proxmox_host'] . '/api2/json/' . ltrim($path, '/'));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $GLOBALS['proxmox_verify_ssl']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $GLOBALS['proxmox_verify_ssl'] ? 2 : 0);
    
    $headers = [
        'Accept: application/json',
        'Cookie: PVEAuthCookie=' . urlencode($ticket)
    ];
    
    if ($method !== 'GET') {
        $headers[] = "CSRFPreventionToken: $csrf";
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['error' => 'Error de conexión: ' . curl_error($ch)];
    }
    
    $result = json_decode($response, true);
    curl_close($ch);
    
    return $result['data'] ?? ['error' => 'Respuesta API inválida'];
}

function getClusterData() {
    $nodes = proxmoxAPI('nodes');
    if (isset($nodes['error'])) return $nodes;
    
    $cluster = ['nodes' => []];
    
    foreach ($nodes as $node) {
        $node_name = $node['node'];
        
        // Obtener estado del nodo
        $status = proxmoxAPI("nodes/$node_name/status");
        if (isset($status['error'])) continue;
        
        // Obtener contenedores
        $containers = proxmoxAPI("nodes/$node_name/lxc");
        if (isset($containers['error'])) $containers = [];
        
        // Obtener máquinas virtuales
        $vms = proxmoxAPI("nodes/$node_name/qemu");
        if (isset($vms['error'])) $vms = [];
        
        // Obtener uso de almacenamiento
        $storage = proxmoxAPI("nodes/$node_name/storage");
        $disk_total = 0;
        $disk_used = 0;
        
        if (!isset($storage['error'])) {
            foreach ($storage as $s) {
                if ($s['active'] == 1) {
                    $disk_total += $s['total'] ?? 0;
                    $disk_used += $s['used'] ?? 0;
                }
            }
        }
        
        $cluster['nodes'][$node_name] = [
            'name' => $node_name,
            'status' => $status,
            'containers' => $containers,
            'vms' => $vms,
            'cpu_usage' => ($status['cpu'] ?? 0) * 100,
            'memory_usage' => ($status['memory']['used'] ?? 0) / ($status['memory']['total'] ?? 1) * 100,
            'disk_usage' => $disk_total > 0 ? ($disk_used / $disk_total) * 100 : 0
        ];
    }
    
    // Calcular estadísticas del cluster
    $total_nodes = count($cluster['nodes']);
    $total_containers = 0;
    $total_vms = 0;
    $total_cpu = 0;
    $used_cpu = 0;
    $total_mem = 0;
    $used_mem = 0;
    $total_disk = 0;
    $used_disk = 0;
    
    foreach ($cluster['nodes'] as $node) {
        $total_containers += count($node['containers']);
        $total_vms += count($node['vms']);
        $total_cpu += 100; // Cada nodo aporta 100%
        $used_cpu += $node['cpu_usage'];
        $total_mem += $node['status']['memory']['total'] ?? 0;
        $used_mem += $node['status']['memory']['used'] ?? 0;
        $total_disk += $node['status']['rootfs']['total'] ?? 0;
        $used_disk += $node['status']['rootfs']['used'] ?? 0;
    }
    
    $cluster['stats'] = [
        'nodes' => $total_nodes,
        'containers' => $total_containers,
        'vms' => $total_vms,
        'cpu_usage' => $total_cpu > 0 ? ($used_cpu / $total_cpu) * 100 : 0,
        'memory_usage' => $total_mem > 0 ? ($used_mem / $total_mem) * 100 : 0,
        'disk_usage' => $total_disk > 0 ? ($used_disk / $total_disk) * 100 : 0
    ];
    
    return $cluster;
}

function generateVNCUrl($node, $vmid, $type = 'lxc') {
    $host = $GLOBALS['proxmox_host'];
    
    if ($type === 'qemu') {
        // Formato para máquinas virtuales (VMs)
        return "$host/?console=kvm&novnc=1&node=$node&resize=off&vmid=$vmid";
    } else {
        // Formato para contenedores LXC
        return "$host/?console=lxc&xtermjs=1&vmid=$vmid&node=$node&cmd=";
    }
}

// Función para manejar acciones de start/stop
function handleVMControl($node, $vmid, $type, $action) {
    $path = "nodes/$node/" . ($type === 'qemu' ? 'qemu' : 'lxc') . "/$vmid/status/$action";
    return proxmoxAPI($path, 'POST');
}

// Procesar acciones si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['node'], $_POST['vmid'], $_POST['type'])) {
    $result = handleVMControl($_POST['node'], $_POST['vmid'], $_POST['type'], $_POST['action']);
    // Recargar la página para ver los cambios
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// ==============================================
// OBTENER DATOS DEL CLUSTER
// ==============================================
$cluster_data = getClusterData();
$error_message = isset($cluster_data['error']) ? $cluster_data['error'] : null;

// ==============================================
// HTML - INTERFAZ DE USUARIO
// ==============================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitorización Proxmox - Infratozz SL</title>
    <link rel="shortcut icon" href="img/Logo.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #0056b3;
            --secondary: #003366;
            --accent: #00a1e0;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --success: #2a9d8f;
            --danger: #e63946;
            --warning: #f4a261;
            --text: #333;
            --text-light: #fff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--dark);
            color: var(--text-light);
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, var(--dark), var(--secondary));
            color: var(--text-light);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            background-color: rgba(26, 26, 46, 0.8);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--accent);
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat {
            background-color: rgba(0,0,0,0.2);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            border-top: 4px solid var(--accent);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .stat i {
            font-size: 2.5rem;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: rgba(255,255,255,0.8);
        }
        
        .chart-container {
            background-color: rgba(26, 26, 46, 0.8);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,161,224,0.3);
        }
        
        .chart-title {
            text-align: center;
            margin-bottom: 1rem;
            font-size: 1.3rem;
            color: var(--accent);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,161,224,0.2);
        }
        
        th {
            background-color: rgba(0,86,179,0.2);
            color: var(--accent);
        }
        
        tr:hover {
            background-color: rgba(0,161,224,0.1);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .online {
            background-color: var(--success);
            color: white;
        }
        
        .offline {
            background-color: var(--danger);
            color: white;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .btn-vnc {
            background-color: var(--accent);
            color: white;
        }
        
        .btn-vnc:hover {
            background-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .btn-start {
            background-color: var(--success);
            color: white;
        }
        
        .btn-start:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .btn-stop {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-stop:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .vm-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .vm-card {
            background-color: rgba(0,0,0,0.2);
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid rgba(0,161,224,0.2);
            transition: transform 0.3s;
        }
        
        .vm-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,161,224,0.1);
        }
        
        .vm-name {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--accent);
            font-size: 1.1rem;
        }
        
        .vm-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .vm-info strong {
            color: var(--accent);
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(to right, var(--accent), #fff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 0 5px rgba(0,161,224,0.3);
        }
        
        .logo-slogan {
            font-size: 0.8rem;
            color: var(--accent);
            margin-top: -0.3rem;
        }
        
        form {
            display: inline;
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .vm-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div class="logo-container">
                    <img src="img/Infratozz-Logo.png" alt="Logo" style="height: 50px;">
                    <div>
                        <div class="logo-text">INFRATOZZ SL</div>
                        <div class="logo-slogan">Monitorización Proxmox</div>
                    </div>
                </div>
                <nav>
                    <a href="index.html" style="color: white; text-decoration: none; margin-left: 1.5rem;">
                        <i class="fas fa-home"></i> Inicio
                    </a>
                    <a href="home.php" style="color: white; text-decoration: none; margin-left: 1.5rem;">
                        <i class="fas fa-tachometer-alt"></i> Panel
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <?php if ($error_message): ?>
            <div class="card" style="border-left-color: var(--danger);">
                <h2>Error</h2>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php else: ?>
            <!-- Estadísticas del cluster -->
            <h1 style="text-align: center; margin-bottom: 2rem; color: var(--accent);">Estado del Cluster Proxmox</h1>
            
            <div class="grid">
                <div class="stat">
                    <i class="fas fa-server"></i>
                    <div class="stat-value"><?php echo $cluster_data['stats']['nodes'] ?? 0; ?></div>
                    <div class="stat-label">Nodos</div>
                </div>
                <div class="stat">
                    <i class="fas fa-microchip"></i>
                    <div class="stat-value"><?php echo round($cluster_data['stats']['cpu_usage'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">Uso de CPU</div>
                </div>
                <div class="stat">
                    <i class="fas fa-memory"></i>
                    <div class="stat-value"><?php echo round($cluster_data['stats']['memory_usage'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">Uso de Memoria</div>
                </div>
                <div class="stat">
                    <i class="fas fa-hdd"></i>
                    <div class="stat-value"><?php echo round($cluster_data['stats']['disk_usage'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">Uso de Disco</div>
                </div>
                <div class="stat">
                    <i class="fas fa-box"></i>
                    <div class="stat-value"><?php echo $cluster_data['stats']['containers'] ?? 0; ?></div>
                    <div class="stat-label">Contenedores</div>
                </div>
                <div class="stat">
                    <i class="fas fa-desktop"></i>
                    <div class="stat-value"><?php echo $cluster_data['stats']['vms'] ?? 0; ?></div>
                    <div class="stat-label">Máquinas Virtuales</div>
                </div>
            </div>

            <!-- Gráficos del cluster -->
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));">
                <div class="chart-container">
                    <h3 class="chart-title">Uso de CPU del Cluster</h3>
                    <canvas id="cpuChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3 class="chart-title">Uso de Memoria del Cluster</h3>
                    <canvas id="memoryChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3 class="chart-title">Uso de Disco del Cluster</h3>
                    <canvas id="diskChart"></canvas>
                </div>
            </div>

            <!-- Nodos del cluster -->
            <div class="card">
                <h2 class="section-title">
                    <i class="fas fa-server"></i> Nodos del Cluster
                </h2>
                
                <?php foreach ($cluster_data['nodes'] as $node): ?>
                    <div style="margin-bottom: 2rem; background-color: rgba(0,0,0,0.1); padding: 1rem; border-radius: 8px;">
                        <h3 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <?php echo htmlspecialchars($node['name']); ?>
                            <span class="badge online">ONLINE</span>
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin: 1rem 0;">
                            <div>
                                <strong><i class="fas fa-microchip"></i> CPU:</strong> <?php echo round($node['cpu_usage'], 1); ?>%
                            </div>
                            <div>
                                <strong><i class="fas fa-memory"></i> Memoria:</strong> <?php echo round($node['memory_usage'], 1); ?>%
                            </div>
                            <div>
                                <strong><i class="fas fa-hdd"></i> Disco:</strong> <?php echo round($node['disk_usage'], 1); ?>%
                            </div>
                            <div>
                                <strong><i class="fas fa-box"></i> Contenedores:</strong> <?php echo count($node['containers']); ?>
                            </div>
                            <div>
                                <strong><i class="fas fa-desktop"></i> Máquinas Virtuales:</strong> <?php echo count($node['vms']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Contenedores LXC -->
            <div class="card">
                <h2 class="section-title">
                    <i class="fas fa-box"></i> Contenedores LXC
                </h2>
                
                <!-- Vista de tabla -->
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>ID</th>
                            <th>Nodo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cluster_data['nodes'] as $node): ?>
                            <?php foreach ($node['containers'] as $container): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($container['name'] ?? 'CT'.$container['vmid']); ?></td>
                                    <td><?php echo $container['vmid']; ?></td>
                                    <td><?php echo htmlspecialchars($node['name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $container['status'] === 'running' ? 'online' : 'offline'; ?>">
                                            <?php echo strtoupper($container['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($container['status'] === 'running'): ?>
                                            <a href="<?php echo generateVNCUrl($node['name'], $container['vmid'], 'lxc'); ?>" 
                                               target="_blank" 
                                               class="btn btn-vnc">
                                                <i class="fas fa-desktop"></i> VNC
                                            </a>
                                            <form method="post" onsubmit="return confirm('¿Detener el contenedor <?php echo htmlspecialchars($container['name'] ?? 'CT'.$container['vmid']); ?>?');">
                                                <input type="hidden" name="node" value="<?php echo htmlspecialchars($node['name']); ?>">
                                                <input type="hidden" name="vmid" value="<?php echo $container['vmid']; ?>">
                                                <input type="hidden" name="type" value="lxc">
                                                <input type="hidden" name="action" value="stop">
                                                <button type="submit" class="btn btn-stop">
                                                    <i class="fas fa-power-off"></i> Apagar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" onsubmit="return confirm('¿Iniciar el contenedor <?php echo htmlspecialchars($container['name'] ?? 'CT'.$container['vmid']); ?>?');">
                                                <input type="hidden" name="node" value="<?php echo htmlspecialchars($node['name']); ?>">
                                                <input type="hidden" name="vmid" value="<?php echo $container['vmid']; ?>">
                                                <input type="hidden" name="type" value="lxc">
                                                <input type="hidden" name="action" value="start">
                                                <button type="submit" class="btn btn-start">
                                                    <i class="fas fa-play"></i> Iniciar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Vista de tarjetas -->
                <h3 style="margin: 2rem 0 1rem 0;"><i class="fas fa-th"></i> Vista de Tarjetas</h3>
                <div class="vm-container">
                    <?php foreach ($cluster_data['nodes'] as $node): ?>
                        <?php foreach ($node['containers'] as $container): ?>
                            <div class="vm-card">
                                <div class="vm-name">
                                    <?php echo htmlspecialchars($container['name'] ?? 'CT'.$container['vmid']); ?>
                                    <span class="badge <?php echo $container['status'] === 'running' ? 'online' : 'offline'; ?>" style="float: right;">
                                        <?php echo strtoupper($container['status']); ?>
                                    </span>
                                </div>
                                <div class="vm-info">
                                    <span><strong>ID:</strong> <?php echo $container['vmid']; ?></span>
                                    <span><strong>Nodo:</strong> <?php echo htmlspecialchars($node['name']); ?></span>
                                    <span><strong>CPU:</strong> <?php echo $container['cpus'] ?? 'N/A'; ?></span>
                                    <span><strong>Memoria:</strong> <?php echo round(($container['mem'] ?? 0) / 1024 / 1024, 2); ?> GB</span>
                                    <?php if (isset($container['uptime'])): ?>
                                        <span><strong>Uptime:</strong> <?php echo gmdate("d H:i:s", $container['uptime']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Máquinas Virtuales -->
            <div class="card">
                <h2 class="section-title">
                    <i class="fas fa-desktop"></i> Máquinas Virtuales
                </h2>
                
                <!-- Vista de tabla -->
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>ID</th>
                            <th>Nodo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cluster_data['nodes'] as $node): ?>
                            <?php foreach ($node['vms'] as $vm): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vm['name'] ?? 'VM'.$vm['vmid']); ?></td>
                                    <td><?php echo $vm['vmid']; ?></td>
                                    <td><?php echo htmlspecialchars($node['name']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $vm['status'] === 'running' ? 'online' : 'offline'; ?>">
                                            <?php echo strtoupper($vm['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($vm['status'] === 'running'): ?>
                                            <a href="<?php echo generateVNCUrl($node['name'], $vm['vmid'], 'qemu'); ?>" 
                                               target="_blank" 
                                               class="btn btn-vnc">
                                                <i class="fas fa-desktop"></i> VNC
                                            </a>
                                            <form method="post" onsubmit="return confirm('¿Detener la máquina virtual <?php echo htmlspecialchars($vm['name'] ?? 'VM'.$vm['vmid']); ?>?');">
                                                <input type="hidden" name="node" value="<?php echo htmlspecialchars($node['name']); ?>">
                                                <input type="hidden" name="vmid" value="<?php echo $vm['vmid']; ?>">
                                                <input type="hidden" name="type" value="qemu">
                                                <input type="hidden" name="action" value="stop">
                                                <button type="submit" class="btn btn-stop">
                                                    <i class="fas fa-power-off"></i> Apagar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" onsubmit="return confirm('¿Iniciar la máquina virtual <?php echo htmlspecialchars($vm['name'] ?? 'VM'.$vm['vmid']); ?>?');">
                                                <input type="hidden" name="node" value="<?php echo htmlspecialchars($node['name']); ?>">
                                                <input type="hidden" name="vmid" value="<?php echo $vm['vmid']; ?>">
                                                <input type="hidden" name="type" value="qemu">
                                                <input type="hidden" name="action" value="start">
                                                <button type="submit" class="btn btn-start">
                                                    <i class="fas fa-play"></i> Iniciar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Vista de tarjetas -->
                <h3 style="margin: 2rem 0 1rem 0;"><i class="fas fa-th"></i> Vista de Tarjetas</h3>
                <div class="vm-container">
                    <?php foreach ($cluster_data['nodes'] as $node): ?>
                        <?php foreach ($node['vms'] as $vm): ?>
                            <div class="vm-card">
                                <div class="vm-name">
                                    <?php echo htmlspecialchars($vm['name'] ?? 'VM'.$vm['vmid']); ?>
                                    <span class="badge <?php echo $vm['status'] === 'running' ? 'online' : 'offline'; ?>" style="float: right;">
                                        <?php echo strtoupper($vm['status']); ?>
                                    </span>
                                </div>
                                <div class="vm-info">
                                    <span><strong>ID:</strong> <?php echo $vm['vmid']; ?></span>
                                    <span><strong>Nodo:</strong> <?php echo htmlspecialchars($node['name']); ?></span>
                                    <span><strong>CPU:</strong> <?php echo $vm['cpus'] ?? 'N/A'; ?></span>
                                    <span><strong>Memoria:</strong> <?php echo round(($vm['mem'] ?? 0) / 1024 / 1024, 2); ?> GB</span>
                                    <?php if (isset($vm['uptime'])): ?>
                                        <span><strong>Uptime:</strong> <?php echo gmdate("d H:i:s", $vm['uptime']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer style="background: linear-gradient(135deg, var(--dark), var(--secondary)); color: white; padding: 2rem 0; margin-top: 3rem;">
        <div class="container" style="text-align: center;">
            <p>&copy; <?php echo date('Y'); ?> Infratozz SL. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        // Gráficos
        const cpuCtx = document.getElementById('cpuChart').getContext('2d');
        new Chart(cpuCtx, {
            type: 'doughnut',
            data: {
                labels: ['Uso', 'Libre'],
                datasets: [{
                    data: [<?php echo round($cluster_data['stats']['cpu_usage'] ?? 0, 1); ?>, 100 - <?php echo round($cluster_data['stats']['cpu_usage'] ?? 0, 1); ?>],
                    backgroundColor: [
                        'rgba(0, 161, 224, 0.8)',
                        'rgba(200, 200, 200, 0.2)'
                    ],
                    borderColor: [
                        'rgba(0, 161, 224, 1)',
                        'rgba(200, 200, 200, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });

        const memoryCtx = document.getElementById('memoryChart').getContext('2d');
        new Chart(memoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['Uso', 'Libre'],
                datasets: [{
                    data: [<?php echo round($cluster_data['stats']['memory_usage'] ?? 0, 1); ?>, 100 - <?php echo round($cluster_data['stats']['memory_usage'] ?? 0, 1); ?>],
                    backgroundColor: [
                        'rgba(42, 157, 143, 0.8)',
                        'rgba(200, 200, 200, 0.2)'
                    ],
                    borderColor: [
                        'rgba(42, 157, 143, 1)',
                        'rgba(200, 200, 200, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });

        const diskCtx = document.getElementById('diskChart').getContext('2d');
        new Chart(diskCtx, {
            type: 'doughnut',
            data: {
                labels: ['Uso', 'Libre'],
                datasets: [{
                    data: [<?php echo round($cluster_data['stats']['disk_usage'] ?? 0, 1); ?>, 100 - <?php echo round($cluster_data['stats']['disk_usage'] ?? 0, 1); ?>],
                    backgroundColor: [
                        'rgba(230, 57, 70, 0.8)',
                        'rgba(200, 200, 200, 0.2)'
                    ],
                    borderColor: [
                        'rgba(230, 57, 70, 1)',
                        'rgba(200, 200, 200, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>