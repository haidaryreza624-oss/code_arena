#include <iostream>

int main() {
    int n;
    std::cin >> n;

    if (n % 15 == 0) {
        std::cout << "FizzBuzz\n";
    } else if (n % 3 == 0) {
        std::cout << "Fizz\n";
    } else if (n % 5 == 0) {
        std::cout << "Buzz\n";
    } else {
        std::cout << n << '\n';
    }

    return 0;
}
