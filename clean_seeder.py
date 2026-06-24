import re

with open('database/seeders/DemoInventorySeeder.php', 'r') as f:
    c = f.read()

# remove use statement
c = c.replace('use App\\Models\\MovementPurpose;\n', '')

# remove purposes() function
c = re.sub(r'    /\*\*\n     \* @return array<string, MovementPurpose>\n     \*/\n    private function purposes\(\): array\n    \{.*?\n    \}\n\n', '', c, flags=re.DOTALL)

# remove $purposes = $this->purposes(); from run()
c = c.replace('            $purposes = $this->purposes();\n', '')

# remove $purposes from the movements method signature and call
c = c.replace('     * @param  array<string, MovementPurpose>  $purposes\n', '')
c = c.replace('array $purposes, ', '')
c = c.replace('$purposes, ', '')

# remove the ?string $purpose parameter from the movement method
c = c.replace('\n        ?string $purpose,', '')

# remove the assignment of movement_purpose_id
c = c.replace('\n            \'movement_purpose_id\' => $purpose ? $purposes[$purpose]->id : null,', '')

# Remove purpose argument from all $this->movement calls
# The pattern is: $this->movement(..., MovementType::Type, $source, $destination, $purpose, $reason, ...)
c = re.sub(r'(\$this->movement\(\$items, \$locations, \$reasons, \$createdBy, [^,]+, [^,]+, MovementType::[A-Za-z]+, (?:null|\'[^\']+\'), (?:null|\'[^\']+\')), (?:null|\'[^\']+\'),', r'\1,', c)

# for seedDailyUsage calls:
c = c.replace('MovementType::StockOut, $source, null, $purpose, null, $pic, $notes, $lines);', 'MovementType::StockOut, $source, null, null, $pic, $notes, $lines);')

# remove 'is_active' => true/false
c = re.sub(r'\s*\'is_active\' => true,', '', c)
c = re.sub(r'\s*\'is_active\' => false,', '', c)

with open('database/seeders/DemoInventorySeeder.php', 'w') as f:
    f.write(c)
