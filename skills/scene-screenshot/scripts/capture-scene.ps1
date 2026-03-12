param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Args
)

npm run capture:scene -- @Args
