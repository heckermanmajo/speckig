type Bare = object
  name: string
  count: int

proc bare_helper(count: int): int =
  result = count
