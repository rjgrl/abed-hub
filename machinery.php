<?php 
include '../components/db_connect.php'; 
$sql = "SELECT * FROM machinery WHERE status = 'Delivered'";
$result = $conn->query($sql);
?>
<h2 class="fw-bold">AFME Inventory</h2>
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Machine Name</th>
                <th>Program</th>
                <th>Year Funded</th>
                <th>Beneficiary</th>
            </tr>
        </thead>
        <tbody>
            <?php while($machine = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $machine['machine_name']; ?></td>
                <td><?php echo $machine['program']; ?></td>
                <td><?php echo $machine['year_funded']; ?></td>
                <td><?php echo $machine['beneficiary']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

