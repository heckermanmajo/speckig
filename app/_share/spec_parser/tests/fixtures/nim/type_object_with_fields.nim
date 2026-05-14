## @spec
## Fixture for object types with per-field specs of mixed primitive types.
## @end-spec

## @spec
## Sensor reading captured at a single point in time.
## @end-spec
type Reading = object
  ## @spec
  ## sensor identifier, monotonically assigned at boot
  ## @end-spec
  id: int
  ## @spec
  ## human-readable label, may be empty until the sensor is named
  ## @end-spec
  label: string
  ## @spec
  ## measured value in degrees Celsius
  ## @end-spec
  value: float
